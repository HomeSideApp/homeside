<?php

namespace App\Actions\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a recipe, cleaning up its cover and step images first.
 */
final class DeleteRecipe
{
    /**
     * @param  Recipe  $recipe  The recipe to delete
     */
    public function execute(Recipe $recipe): void
    {
        // Clean up cover image if exists
        if ($recipe->cover_image_path && Storage::disk('public')->exists($recipe->cover_image_path)) {
            Storage::disk('public')->delete($recipe->cover_image_path);
        }

        // Clean up step images
        foreach ($recipe->steps as $step) {
            if ($step->image_path && Storage::disk('public')->exists($step->image_path)) {
                Storage::disk('public')->delete($step->image_path);
            }
        }

        // Cascading deletes handle the rest (sections, ingredients, steps, timers, cookware, tags, household_recipes)
        $recipe->delete();
    }
}
