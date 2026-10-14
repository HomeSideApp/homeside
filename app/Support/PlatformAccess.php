<?php

namespace App\Support;

use App\Contracts\EntitlementGate;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\Household;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformAccess
{
    /**
     * Determine whether the user may manage platform-level assignments.
     *
     * Managing requires BOTH independent axes to allow access: an approved
     * account that is currently active, with the role-assignment permission.
     */
    public function canManage(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /**
     * Determine whether the user counts as a platform administrator.
     *
     * This is the definition the "last administrator" safeguard protects: an
     * approved, active user who can assign roles. A suspended or blocked admin
     * does not count, so they can never be the last one left.
     */
    public function isAdministrator(User $user): bool
    {
        return $user->approval_status === ApprovalStatus::Approved->value
            && $user->account_status === AccountStatus::Active
            && $user->getPermissionRouteNames()->contains('admin.users.assign-roles');
    }

    /**
     * Determine whether another approved, active administrator exists besides this user.
     *
     * Used by the last-administrator safeguard so an administrator can never
     * lock the platform out of its own management.
     */
    public function hasAnotherAdministrator(User $user): bool
    {
        return User::query()
            ->whereKeyNot($user->id)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereHas('roles.permissions', fn ($permissions) => $permissions->where('route_name', 'admin.users.assign-roles'))
            ->exists();
    }

    /**
     * Decide whether the user is STRUCTURALLY allowed to create a household.
     *
     * This answers "may this user ever create a household?" and deliberately
     * does NOT answer "do they have capacity for one more right now?". A
     * structural denial means the user may never create a household and must
     * stay a 403; capacity is a different question, owned by
     * `hasHouseholdOwnershipCapacity()`, and its authoritative refusal must
     * stay the 402 raised inside the creation transaction.
     *
     * Only structure is read here: the installation's household-creation
     * policy, `households_enabled`, an active account status, and the tri-state
     * override (`false` forbids outright, `null` delegates, and the deliberately
     * absent `true` state is not a grant). `null` is therefore NOT permission
     * on its own; it only means "not structurally forbidden", and the capacity
     * read still decides whether a creation may proceed.
     *
     * This is the predicate the HouseholdPolicy and the request authorization
     * use, so keeping the entitlement out of it is what preserves the 403/402
     * split for the API, the web form and `CreateHousehold`.
     */
    public function canCreateHouseholds(User $user): bool
    {
        if (! app(RegistrationSettings::class)->householdCreationPolicy()->allowsCreation()) {
            return false;
        }

        return $user->households_enabled
            && $user->account_status === AccountStatus::Active
            && $user->can_create_households_override !== false;
    }

    /**
     * Answer the COMBINED question: structure AND current ownership capacity.
     *
     * Use this only for presentation and pre-flight decisions, such as the
     * `can_create_households` serialized prop and the advertised API operation
     * list, so a control is hidden for a user who is at their cap. NEVER use it
     * as the authoritative refusal: a false here must not be turned into a 403,
     * because the real quota refusal has to be the 402 that
     * `assertHouseholdOwnershipCapacity()` throws inside the creation
     * transaction.
     */
    public function mayCreateHouseholdNow(User $user): bool
    {
        return $this->canCreateHouseholds($user) && $this->hasHouseholdOwnershipCapacity($user);
    }

    /**
     * Ask the entitlement gate whether the user can own one more household.
     *
     * This is the CAPACITY question on its own: the user may not create
     * ANOTHER household right now, even though they are structurally allowed to
     * create one in principle. It is public because presentation call sites
     * need to distinguish the two answers, while the authoritative refusal for
     * an actual creation stays the entitlement assertion inside
     * `CreateHousehold`'s transaction, which produces the 402 outcome.
     *
     * The read happens several times per serialized user (UserResource via
     * ApiOperationPermissions, route decisions), so the answer is memoized on
     * the request attributes, the same convention HasPermissionsTrait and
     * CapabilityRegistry use, with a plain read in CLI runs that have no
     * request to memoize on. It is deliberately per request, never static
     * state.
     *
     * Only the entitlement answer is memoized. The installation policy and the
     * per-user tri-state are re-read on every `canCreateHouseholds()` call, so
     * an administrative change inside the same request is never masked.
     */
    public function hasHouseholdOwnershipCapacity(User $user): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $this->assertsHouseholdOwnershipCapacity($user);
        }

        $request = request();
        $key = 'platform_access.household_ownership_capacity.'.$user->id;

        if ($request->attributes->has($key)) {
            return (bool) $request->attributes->get($key);
        }

        $allowed = $this->assertsHouseholdOwnershipCapacity($user);
        $request->attributes->set($key, $allowed);

        return $allowed;
    }

    /**
     * Translate the entitlement gate's quota outcome into a boolean.
     */
    private function assertsHouseholdOwnershipCapacity(User $user): bool
    {
        try {
            app(EntitlementGate::class)->assertHouseholdOwnershipCapacity($user);

            return true;
        } catch (EntitlementQuotaExceededException) {
            return false;
        }
    }

    /** @param list<string> $slugs */
    public function assignRoles(User $actor, User $user, array $slugs): void
    {
        abort_unless($this->canManage($actor), 403);
        DB::transaction(function () use ($user, $slugs): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->first();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $roles = Role::query()->whereIn('slug', array_unique([...$slugs, 'user']))->get();
            $retainsManagement = $roles->contains(fn (Role $role): bool => $role->permissions()->where('route_name', 'admin.users.assign-roles')->exists());
            if ($this->canManage($user) && ! $retainsManagement) {
                $this->ensureAnotherAdministrator($user);
            }
            $user->roles()->sync($roles->pluck('id'));
            request()->attributes->remove('global_permissions.'.$user->id);
        }, 3);
    }

    public function ensureCanDelete(User $user): void
    {
        if (Household::query()->where('owner_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['user' => __('app.permissions.owner_transfer_required')]);
        }
        Role::query()->where('slug', 'admin')->lockForUpdate()->first();
        if ($this->canManage($user)) {
            $this->ensureAnotherAdministrator($user);
        }
    }

    /** @param array<int, string> $permissionIds */
    public function ensureRoleChange(Role $role, array $permissionIds = []): void
    {
        Role::query()->where('slug', 'admin')->lockForUpdate()->first();
        $management = $role->permissions()->where('route_name', 'admin.users.assign-roles')->first();
        if ($management === null || in_array($management->id, $permissionIds, true)) {
            return;
        }
        $affected = $role->users()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('account_status', AccountStatus::Active->value)
            ->exists();
        $remaining = User::query()
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->where('account_status', AccountStatus::Active->value)
            ->whereHas('roles', fn ($roles) => $roles->where('roles.id', '!=', $role->id)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('route_name', 'admin.users.assign-roles')))->exists();
        if ($affected && ! $remaining) {
            throw ValidationException::withMessages(['permissions' => __('app.permissions.last_platform_admin')]);
        }
    }

    private function ensureAnotherAdministrator(User $user): void
    {
        if (! $this->hasAnotherAdministrator($user)) {
            throw ValidationException::withMessages(['roles' => __('app.permissions.last_platform_admin')]);
        }
    }
}
