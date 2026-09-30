<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('contacts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $this->allowed($user, $contact, 'view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->getPermissionRouteNames()->contains('contacts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $this->allowed($user, $contact, 'update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $this->allowed($user, $contact, 'delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Contact $contact): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Contact $contact): bool
    {
        return false;
    }

    private function allowed(User $user, Contact $contact, string $operation): bool
    {
        $permission = $contact->household_id === null ? 'contacts.'.$operation : 'contacts.home.'.$operation;

        if (! $user->getPermissionRouteNames()->contains($permission)) {
            return false;
        }

        return $contact->household_id === null
            ? $contact->user_id === $user->id
            : $contact->household?->isMember($user) === true;
    }
}
