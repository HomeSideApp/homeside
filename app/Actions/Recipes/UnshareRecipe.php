<?php

namespace App\Actions\Recipes;

use App\Models\Household;
use App\Models\HouseholdRecipe;
use App\Models\Recipe;

/**
 * Removes the share of a recipe with a household.
 */
final class UnshareRecipe
{
    /**
     * @param  Recipe  $recipe  The recipe to unshare
     * @param  Household  $household  The household to unshare from
     */
    public function execute(Recipe $recipe, Household $household): void
    {
        HouseholdRecipe::where('household_id', $household->id)
            ->where('recipe_id', $recipe->id)
            ->delete();
    }
}
