<?php

namespace App\Console\Commands;

use App\Enums\HouseholdAccessProfile;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdInviteLink;
use App\Models\HouseholdMember;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MigrateHouseholdPermissions extends Command
{
    protected $signature = 'households:migrate-permissions {--dry-run : Preview without changing assignments}';

    protected $description = 'Convert legacy global roles to household access, ownership and platform roles atomically';

    public function handle(): int
    {
        try {
            return DB::transaction(function (): int {
                if (! $this->option('dry-run')) {
                    foreach (['users' => ['can_create_households'], 'households' => ['owner_id'], 'household_members' => ['access_profile', 'module_access'], 'household_invitations' => ['access_profile', 'module_access'], 'household_invite_links' => ['access_profile', 'module_access']] as $table => $columns) {
                        if (! Schema::hasColumns($table, $columns)) {
                            throw new RuntimeException('Apply the household permission schema migration before converting data.');
                        }
                    }
                }
                $users = User::query()->with('roles.permissions')->orderBy('id')->lockForUpdate()->get();
                $households = Household::query()->with('members')->orderBy('id')->lockForUpdate()->get();
                $assignments = [];
                foreach ($households as $household) {
                    $ownerId = $household->owner_id ?? $household->created_by;
                    $owner = $household->members->firstWhere('user_id', $ownerId);
                    if (! $owner || $owner->role !== HouseholdRole::Admin || ! $users->contains('id', $ownerId)) {
                        throw new RuntimeException('Invalid owner/admin membership for household '.$household->id);
                    }
                    $this->line('Ownership '.$household->id.' => '.$ownerId);
                    foreach ($household->members as $member) {
                        $user = $users->firstWhere('id', $member->user_id);
                        if (! $user) {
                            throw new RuntimeException('Missing user for membership '.$member->id);
                        }
                        $rawProfile = $member->getRawOriginal('access_profile');
                        if ($rawProfile !== null && HouseholdAccessProfile::tryFrom($rawProfile) === null) {
                            throw new RuntimeException('Invalid profile for membership '.$member->id);
                        }
                        if ($member->access_profile !== null) {
                            if (array_diff(array_keys($member->module_access ?? []), ['shopping_lists', 'recipes', 'economy', 'contacts']) !== []
                                || array_diff(array_values($member->module_access ?? []), ['none', 'read', 'write']) !== []
                                || $member->access_profile->role() !== $member->role) {
                                throw new RuntimeException('Invalid access for membership '.$member->id);
                            }

                            continue;
                        }
                        [$profile, $access] = $this->legacyAccess($user, $member);
                        $assignments[$member->id] = ['access_profile' => $profile->value, 'module_access' => $access];
                        $this->line('Access '.$member->id.' => '.json_encode($assignments[$member->id], JSON_THROW_ON_ERROR));
                    }
                }
                foreach ([HouseholdInvitation::class, HouseholdInviteLink::class] as $model) {
                    foreach ($model::query()->orderBy('id')->lockForUpdate()->get() as $invitation) {
                        $rawProfile = $invitation->getRawOriginal('access_profile');
                        $access = $invitation->getAttribute('module_access');
                        if (($rawProfile !== null && HouseholdAccessProfile::tryFrom($rawProfile) === null)
                            || ($access !== null && (! is_array($access)
                                || array_diff(array_keys($access), ['shopping_lists', 'recipes', 'economy', 'contacts']) !== []
                                || array_diff(array_values($access), ['none', 'read', 'write']) !== []))) {
                            throw new RuntimeException('Invalid invitation access for '.$invitation->id);
                        }
                        $this->line('Invitation '.$invitation->id.' => '.($rawProfile ?? 'member').'; '.json_encode($access ?? HouseholdAccessProfile::Member->permissions(), JSON_THROW_ON_ERROR));
                    }
                }
                foreach ($users as $user) {
                    $slugs = $user->roles->pluck('slug')->all();
                    $this->line('Roles '.$user->id.' => '.implode(',', array_unique([...array_diff($slugs, ['readonly', 'lists-reader', 'lists-editor', 'user-invitation']), ...($user->approval_status === 'approved' ? ['user'] : [])])).'; create households = '.(in_array('user-invitation', $slugs, true) ? 'no' : (($user->getRawOriginal('can_create_households') ?? true) ? 'yes' : 'no')));
                }
                if ($this->option('dry-run')) {
                    $this->info('Dry run: no changes.');

                    return self::SUCCESS;
                }
                foreach ($households as $household) {
                    $household->update(['owner_id' => $household->owner_id ?? $household->created_by]);
                }
                foreach ($assignments as $id => $attributes) {
                    HouseholdMember::query()->findOrFail($id)->update($attributes);
                }
                foreach ([HouseholdInvitation::class, HouseholdInviteLink::class] as $model) {
                    $model::query()->whereNull('access_profile')->each(fn ($invitation) => $invitation->update(HouseholdAccessProfile::attributes([])));
                }
                $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
                foreach ($users as $user) {
                    if ($user->roles->contains('slug', 'user-invitation')) {
                        $user->update(['can_create_households' => false]);
                    }
                    if ($user->approval_status === 'approved') {
                        $user->assignRole('user');
                    }
                }
                Role::query()->whereIn('slug', ['readonly', 'lists-reader', 'lists-editor', 'user-invitation'])->get()->each(function (Role $role): void {
                    $role->users()->detach();
                    $role->permissions()->detach();
                    $role->delete();
                });
                DB::afterCommit(fn () => Cache::flush());
                $this->info('Permissions converted successfully.');

                return self::SUCCESS;
            });
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /** @return array{HouseholdAccessProfile, array<string, string>} */
    private function legacyAccess(User $user, HouseholdMember $member): array
    {
        if ($member->role === HouseholdRole::Admin) {
            return [HouseholdAccessProfile::Admin, HouseholdAccessProfile::Admin->permissions()];
        }
        $levels = array_fill_keys(['shopping_lists', 'recipes', 'economy', 'contacts'], 'none');
        $rank = ['none' => 0, 'read' => 1, 'write' => 2];
        foreach ($user->roles as $role) {
            $preset = match ($role->slug) {
                'admin', 'user', 'user-invitation' => HouseholdAccessProfile::Member,
                'readonly', 'lists-reader' => HouseholdAccessProfile::Reader,
                'lists-editor' => HouseholdAccessProfile::Buyer,
                default => null,
            };
            $roleLevels = $preset?->permissions() ?? array_fill_keys(array_keys($levels), 'none');
            if ($preset === null) {
                foreach ($role->permissions as $permission) {
                    foreach (['shopping_lists' => 'households.lists.', 'recipes' => 'households.recipes.', 'economy' => 'households.economy.', 'contacts' => 'contacts.home.'] as $area => $prefix) {
                        if (str_starts_with($permission->route_name, $prefix)) {
                            $level = preg_match('/\\.(index|show|view|totals|overview|search-products)$/', $permission->route_name) ? 'read' : 'write';
                            if ($rank[$level] > $rank[$roleLevels[$area]]) {
                                $roleLevels[$area] = $level;
                            }
                        }
                    }
                }
            }
            foreach ($levels as $area => $level) {
                if ($rank[$roleLevels[$area]] > $rank[$level]) {
                    $levels[$area] = $roleLevels[$area];
                }
            }
        }
        foreach ([HouseholdAccessProfile::Member, HouseholdAccessProfile::Buyer, HouseholdAccessProfile::Reader] as $profile) {
            if ($profile->permissions() === $levels) {
                return [$profile, $levels];
            }
        }

        return [HouseholdAccessProfile::Member, $levels];
    }
}
