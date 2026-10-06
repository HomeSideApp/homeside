<?php

namespace App\Actions\Recipes;

use App\Models\Household;
use App\Models\HouseholdRecipe;
use App\Models\Recipe;
use App\Models\User;
use App\Support\HouseholdAccess;
use Illuminate\Support\Facades\Gate;

/**
 * Removes the share of a recipe with a household.
 */
final class UnshareRecipe
{
    /**
     * @param  Recipe  $recipe  The recipe to unshare
     * @param  Household  $household  The household to unshare from
     */
    public function execute(Recipe $recipe, Household $household, User $actor): void
    {
        Gate::forUser($actor)->authorize('unshare', $recipe);
        abort_unless(app(HouseholdAccess::class)->allows($actor, $household, 'recipes', true), 403);
        HouseholdRecipe::where('household_id', $household->id)
            ->where('recipe_id', $recipe->id)
            ->delete();
    }
}
