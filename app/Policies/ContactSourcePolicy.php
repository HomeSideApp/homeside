<?php

namespace App\Policies;

use App\Models\ContactSource;
use App\Models\User;

class ContactSourcePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('contacts.sources.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ContactSource $contactSource): bool
    {
        return $this->allowed($user, $contactSource, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('contacts.sources.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContactSource $contactSource): bool
    {
        return $this->allowed($user, $contactSource, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContactSource $contactSource): bool
    {
        return $this->allowed($user, $contactSource, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ContactSource $contactSource): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ContactSource $contactSource): bool
    {
        return false;
    }

    public function sync(User $user, ContactSource $contactSource): bool
    {
        return $this->allowed($user, $contactSource, 'sync');
    }

    private function allowed(User $user, ContactSource $source, string $operation): bool
    {
        if (! $user->getPermissionRouteNames()->contains('contacts.sources.'.$operation)) {
            return false;
        }

        return $source->household_id === null
            ? $source->user_id === $user->id
            : $source->household?->isMember($user) === true;
    }
}
