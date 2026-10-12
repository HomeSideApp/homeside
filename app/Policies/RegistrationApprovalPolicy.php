<?php

namespace App\Policies;

use App\Models\User;

/**
 * Class RegistrationApprovalPolicy
 *
 * Authorization for the registration-approval workflow. It deliberately owns a
 * DIFFERENT privilege set from role assignment: being able to approve a
 * registration must never grant `admin.users.assign-roles`, and assigning roles
 * must never grant approval. The two are separate capabilities that an
 * administrator may combine, not implications of one another.
 */
final class RegistrationApprovalPolicy
{
    /**
     * Determine whether the actor may view the registration queue.
     */
    public function view(User $actor, ?User $target = null): bool
    {
        return $this->allowed($actor, 'admin.registrations.view');
    }

    /**
     * Determine whether the actor may approve the target's registration.
     */
    public function approve(User $actor, User $target): bool
    {
        return $this->allowed($actor, 'admin.registrations.approve');
    }

    /**
     * Determine whether the actor may reject the target's registration.
     */
    public function reject(User $actor, User $target): bool
    {
        return $this->allowed($actor, 'admin.registrations.reject');
    }

    /**
     * Determine whether the actor may release a prior rejection.
     */
    public function releaseRejection(User $actor, User $target): bool
    {
        return $this->allowed($actor, 'admin.registrations.release-rejection');
    }

    /**
     * Determine whether the actor may manage the registration policies.
     */
    public function manageSettings(User $actor, ?User $target = null): bool
    {
        return $this->allowed($actor, 'admin.registrations.manage-settings');
    }

    /**
     * Require effective access to the application plus the named permission.
     *
     * A suspended or blocked administrator loses every ability here, matching
     * the rule that account status is enforced on every request.
     */
    private function allowed(User $actor, string $permission): bool
    {
        return $actor->canAccessApplication()
            && $actor->getPermissionRouteNames()->contains($permission);
    }
}
