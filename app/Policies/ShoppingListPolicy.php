<?php

namespace App\Policies;

use App\Models\ShoppingList;
use App\Models\User;
use App\Support\HouseholdAccess;

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
        return $this->canAccess($user, $list, true);
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
        return $this->canAccess($user, $list, true);
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
        return $this->canAccess($user, $list, true);
    }

    /**
     * Resolve access from the list's current ownership scope.
     */
    private function canAccess(User $user, ShoppingList $list, bool $write = false): bool
    {
        if ($list->household_id === null) {
            return $list->created_by === $user->id;
        }

        if (! $user->households_enabled) {
            return false;
        }

        return app(HouseholdAccess::class)->allows($user, $list->household_id, 'shopping_lists', $write);
    }
}
