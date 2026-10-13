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
     * Decide whether the user may create a new household.
     *
     * The decision is delegated, never defaulted. The installation policy can
     * only refuse: `denied` returns false regardless of anything else, while
     * `allowed` leaves the rest of the decision untouched. `null` on
     * `can_create_households_override` means "ask the policy and the
     * entitlement gate", `false` is an explicit prohibition, and the
     * deliberately absent `true` state is not a grant. A null override is
     * therefore NOT permission: the entitlement gate is consulted and a denied
     * entitlement yields false.
     */
    public function canCreateHouseholds(User $user): bool
    {
        if (! app(RegistrationSettings::class)->householdCreationPolicy()->allowsCreation()) {
            return false;
        }

        if (! $user->households_enabled
            || $user->account_status !== AccountStatus::Active
            || $user->can_create_households_override === false) {
            return false;
        }

        return $this->hasHouseholdOwnershipCapacity($user);
    }

    /**
     * Ask the entitlement gate whether the user can own one more household.
     *
     * This entitlement read now runs on every `canCreateHouseholds()` call, and
     * that read happens several times per serialized user (UserResource via
     * ApiOperationPermissions, request authorization, route decisions). The
     * answer is therefore memoized on the request attributes, the same
     * convention HasPermissionsTrait and CapabilityRegistry use, with a plain
     * read in CLI runs that have no request to memoize on. It is deliberately
     * per request, never static state.
     *
     * Only the entitlement answer is memoized. The installation policy and the
     * per-user tri-state are re-read on every call, so an administrative change
     * inside the same request is never masked.
     *
     * The authoritative capacity decision for an actual creation is NOT this
     * read: CreateHousehold asserts the same gate inside its transaction, where
     * the package's billing-account row lock serializes concurrent attempts.
     */
    private function hasHouseholdOwnershipCapacity(User $user): bool
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
