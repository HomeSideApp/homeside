<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;
use App\Support\HouseholdAccess;

final class HouseholdRecipePolicy
{
    /**
     * Check if the user can view household recipes.
     *
     * Access: household member.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function view(User $user, Household $household): bool
    {
        return app(HouseholdAccess::class)->allows($user, $household, 'recipes');
    }

    /**
     * Check if the user can share a recipe with the household.
     *
     * Access: recipe owner.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function share(User $user, Household $household): bool
    {
        return app(HouseholdAccess::class)->allows($user, $household, 'recipes', true);
    }

    /**
     * Check if the user can unshare a recipe from the household.
     *
     * Access: recipe owner OR household admin.
     *
     * @param  User  $user  The authenticated user.
     * @param  Household  $household  The household model instance.
     * @return bool True on success, false otherwise.
     */
    public function unshare(User $user, Household $household): bool
    {
        return app(HouseholdAccess::class)->allows($user, $household, 'recipes', true);
    }
}
