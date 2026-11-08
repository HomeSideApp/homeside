<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PlatformAccess;

/**
 * Class UserPolicy
 *
 * Platform-level lifecycle authorizations over another user: suspension,
 * blocking, unblocking, deletion and delete-and-block.
 *
 * Every destructive ability builds on the existing last-administrator
 * safeguard: an actor can never take an action that would leave the platform
 * without an approved, active administrator. Because the platform only counts
 * ACTIVE administrators, an actor who is the sole administrator cannot suspend,
 * block or delete themselves; when another administrator exists, self-service
 * account deletion remains possible.
 *
 * Blocking here changes the account row's `account_status`. The stronger,
 * identity-level block lives in RegistrationBlock and is authorized by its own
 * permission (`admin.users.block` vs `admin.registration-blocks.release`).
 */
final class UserPolicy
{
    /**
     * Determine whether the actor may suspend the target.
     */
    public function suspend(User $actor, User $target): bool
    {
        return $this->canRestrict($actor, $target, 'admin.users.suspend');
    }

    /**
     * Determine whether the actor may lift a suspension.
     *
     * There is no separate `admin.users.unsuspend` permission: whoever may
     * suspend may also restore.
     */
    public function unsuspend(User $actor, User $target): bool
    {
        return $this->allowed($actor, 'admin.users.suspend');
    }

    /**
     * Determine whether the actor may block the target.
     */
    public function block(User $actor, User $target): bool
    {
        return $this->canRestrict($actor, $target, 'admin.users.block');
    }

    /**
     * Determine whether the actor may unblock the target.
     */
    public function unblock(User $actor, User $target): bool
    {
        return $this->allowed($actor, 'admin.users.unblock');
    }

    /**
     * Determine whether the actor may delete the target.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->canRestrict($actor, $target, 'admin.users.delete');
    }

    /**
     * Determine whether the actor may delete the target and block its identity.
     */
    public function deleteAndBlock(User $actor, User $target): bool
    {
        return $this->canRestrict($actor, $target, 'admin.users.delete-and-block');
    }

    /**
     * Require the permission and that the platform keeps another administrator.
     *
     * Applies to every ability that can remove management capacity: suspend,
     * block, delete and delete-and-block.
     */
    private function canRestrict(User $actor, User $target, string $permission): bool
    {
        if (! $this->allowed($actor, $permission)) {
            return false;
        }

        return app(PlatformAccess::class)->hasAnotherAdministrator($target);
    }

    /**
     * Require effective access to the application plus the named permission.
     */
    private function allowed(User $actor, string $permission): bool
    {
        return $actor->canAccessApplication()
            && $actor->getPermissionRouteNames()->contains($permission);
    }
}
