<?php

namespace App\Services\Recipes\Exporter;

use App\Data\Recipes\RecipeData;
use App\Services\Recipes\CooklangSerializer;

/**
 * Exports recipes to Cooklang format using the shared serializer.
 */
final class CooklangRecipeExporter implements RecipeExporter
{
    public function __construct(
        private CooklangSerializer $serializer,
    ) {}

    /**
     * Export a recipe to Cooklang text.
     *
     * @param  RecipeData  $recipe  The recipe model instance.
     * @return string A string value.
     */
    public function export(RecipeData $recipe): string
    {
        return $this->serializer->serializeRecipe($recipe);
    }
}
