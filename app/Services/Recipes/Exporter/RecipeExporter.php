<?php

namespace App\Services\Recipes\Exporter;

use App\Data\Recipes\RecipeData;

/**
 * Contract for recipe exporters that render RecipeData as a string.
 */
interface RecipeExporter
{
    /**
     * Export a recipe to the target format.
     *
     * @param  RecipeData  $recipe  The recipe model instance.
     * @return string A string value.
     */
    public function export(RecipeData $recipe): string;
}
