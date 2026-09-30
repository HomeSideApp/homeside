<?php

namespace App\Policies;

use App\Models\ContactCollection;
use App\Models\User;

class ContactCollectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ContactCollection $contactCollection): bool
    {
        return $user->can('view', $contactCollection->source);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ContactCollection $contactCollection): bool
    {
        return $user->can('update', $contactCollection->source);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ContactCollection $contactCollection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ContactCollection $contactCollection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ContactCollection $contactCollection): bool
    {
        return false;
    }

    public function sync(User $user, ContactCollection $contactCollection): bool
    {
        return $user->can('sync', $contactCollection->source);
    }
}
