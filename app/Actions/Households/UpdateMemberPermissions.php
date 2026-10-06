<?php

namespace App\Actions\Households;

use App\Enums\HouseholdAccessProfile;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateMemberPermissions
{
    /** @param array<string, mixed> $attributes */
    public function execute(Household $household, HouseholdMember $member, User $actor, array $attributes): HouseholdMember
    {
        return DB::transaction(function () use ($household, $member, $actor, $attributes): HouseholdMember {
            $locked = Household::query()->lockForUpdate()->findOrFail($household->id);
            abort_unless($locked->isAdmin($actor), 403);
            $member = $locked->members()->lockForUpdate()->findOrFail($member->id);
            $profile = isset($attributes['access_profile'])
                ? HouseholdAccessProfile::from($attributes['access_profile'])
                : ($member->access_profile ?? ($member->role === HouseholdRole::Admin ? HouseholdAccessProfile::Admin : HouseholdAccessProfile::Member));
            $permissions = array_replace(isset($attributes['access_profile']) ? $profile->permissions() : $member->permissions(), $attributes['module_access'] ?? []);
            if ($member->role === HouseholdRole::Admin && $profile->role() !== HouseholdRole::Admin) {
                if ($locked->owner_id === $member->user_id) {
                    throw ValidationException::withMessages(['access_profile' => __('app.permissions.owner_transfer_required')]);
                }
                if ($locked->members()->where('role', HouseholdRole::Admin)->count() <= 1) {
                    throw ValidationException::withMessages(['access_profile' => __('app.errors.cannot_remove_only_admin')]);
                }
            }
            $member->update(['role' => $profile->role(), 'access_profile' => $profile, 'module_access' => $permissions]);
            app(HouseholdAccess::class)->forget($member->user_id);

            return $member->refresh();
        }, 3);
    }
}
