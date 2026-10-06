<?php

namespace App\Support;

use App\Models\Household;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformAccess
{
    public function canManage(User $user): bool
    {
        return $user->approval_status === 'approved' && $user->getPermissionRouteNames()->contains('admin.users.assign-roles');
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
        $affected = $role->users()->where('approval_status', 'approved')->exists();
        $remaining = User::query()->where('approval_status', 'approved')
            ->whereHas('roles', fn ($roles) => $roles->where('roles.id', '!=', $role->id)
                ->whereHas('permissions', fn ($permissions) => $permissions->where('route_name', 'admin.users.assign-roles')))->exists();
        if ($affected && ! $remaining) {
            throw ValidationException::withMessages(['permissions' => __('app.permissions.last_platform_admin')]);
        }
    }

    private function ensureAnotherAdministrator(User $user): void
    {
        $exists = User::query()->whereKeyNot($user->id)->where('approval_status', 'approved')
            ->whereHas('roles.permissions', fn ($permissions) => $permissions->where('route_name', 'admin.users.assign-roles'))->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['roles' => __('app.permissions.last_platform_admin')]);
        }
    }
}
