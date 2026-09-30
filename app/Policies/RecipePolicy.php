<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

final class RecipePolicy
{
    /**
     * Check if the user can view the recipe.
     *
     * Access: owner OR household member where recipe is shared.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, Recipe $recipe): bool
    {
        // Owner can always view
        if ($recipe->owner_id === $user->id) {
            return true;
        }

        // Household member where recipe is shared
        return $recipe->householdShares()
            ->whereHas('household.members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->exists();
    }

    /**
     * Check if the user can create recipes.
     *
     * Access: any authenticated user.
     *
     * @param  User  $user  The authenticated user.
     * @return bool True on success, false otherwise.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Check if the user can update the recipe.
     *
     * Access: owner only.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }

    /**
     * Check if the user can delete the recipe.
     *
     * Access: owner only.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function delete(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }

    /**
     * Check if the user can manage the recipe (settings, metadata).
     *
     * Access: owner only.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function manage(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }

    /**
     * Check if the user can share the recipe with households.
     *
     * Access: owner only.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function share(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }

    /**
     * Check if the user can unshare the recipe from a household.
     *
     * Access: owner OR household admin.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function unshare(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }

    /**
     * Check if the user can fork the recipe.
     *
     * Access: user can view the recipe AND is member of an active household.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function fork(User $user, Recipe $recipe): bool
    {
        return $this->view($user, $recipe) && $user->active_household_id !== null;
    }

    /**
     * Check if the user can export the recipe.
     *
     * Access: owner only.
     *
     * @param  User  $user  The authenticated user.
     * @param  Recipe  $recipe  The recipe model instance.
     * @return bool True on success, false otherwise.
     */
    public function export(User $user, Recipe $recipe): bool
    {
        return $recipe->owner_id === $user->id;
    }
}
