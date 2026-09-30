<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;

/**
 * Loads a recipe with its complete structure for display or editing.
 */
final class GetRecipe
{
    /**
     * @param  Recipe  $recipe  The recipe to load
     * @return array{recipe: Recipe}
     */
    public function execute(Recipe $recipe): array
    {
        $recipe->load([
            'sections',
            'ingredients.product',
            'steps.timers',
            'steps.recipeReferences.referencedRecipe',
            'steps.cookware',
            'steps.ingredients',
            'cookware',
            'tags',
            'owner',
            'derivedFrom',
            'collection',
        ]);

        return [
            'recipe' => $recipe,
        ];
    }
}
