<?php

namespace App\Actions\Recipes;

use App\Models\Household;
use App\Models\HouseholdRecipe;
use App\Models\Recipe;
use App\Models\User;

/**
 * Shares a recipe with a household, keeping the share idempotent.
 */
final class ShareRecipe
{
    /**
     * @param  Recipe  $recipe  The recipe to share
     * @param  Household  $household  The household to share with
     * @param  User  $actor  The user sharing the recipe
     * @return HouseholdRecipe The HouseholdRecipe value.
     */
    public function execute(Recipe $recipe, Household $household, User $actor): HouseholdRecipe
    {
        // Check if already shared
        $existing = HouseholdRecipe::where('household_id', $household->id)
            ->where('recipe_id', $recipe->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return HouseholdRecipe::create([
            'household_id' => $household->id,
            'recipe_id' => $recipe->id,
            'shared_by' => $actor->id,
        ]);
    }
}
