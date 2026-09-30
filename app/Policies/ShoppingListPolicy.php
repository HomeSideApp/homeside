<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;

final class ShoppingListPolicy
{
    /**
     * Check if the user can view the shopping list.
     *
     * Access: owner for a personal list, or a current household member.
     *
     * @param  User  $user  The authenticated user.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, ShoppingList $list): bool
    {
        return $this->canAccess($user, $list);
    }

    /**
     * Check if the user can update the shopping list.
     *
     * Access: owner for a personal list, or a current household member.
     *
     * @param  User  $user  The authenticated user.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @return bool True on success, false otherwise.
     */
    public function update(User $user, ShoppingList $list): bool
    {
        return $this->canAccess($user, $list);
    }

    /**
     * Check if the user can delete the shopping list.
     *
     * Access: owner for a personal list, or a current household member.
     *
     * @param  User  $user  The authenticated user.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, ShoppingList $list): bool
    {
        return $this->canAccess($user, $list);
    }

    /**
     * Check if the user can manage the shopping list properties (name, etc.).
     *
     * Access: owner for a personal list, or a current household member.
     *
     * @param  User  $user  The authenticated user.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @return bool True on success, false otherwise.
     */
    public function manage(User $user, ShoppingList $list): bool
    {
        return $this->canAccess($user, $list);
    }

    /**
     * Resolve access from the list's current ownership scope.
     */
    private function canAccess(User $user, ShoppingList $list): bool
    {
        if ($list->household_id === null) {
            return $list->created_by === $user->id;
        }

        if (! $user->households_enabled) {
            return false;
        }

        return $user->householdMemberships()
            ->where('household_id', $list->household_id)
            ->exists();
    }
}
