<?php

namespace App\Services\Recipes\Importer;

use App\Data\Recipes\RecipeImportResult;
use Illuminate\Support\Collection;

/**
 * Contract for importing recipes from various sources.
 */
interface RecipeImporter
{
    /**
     * Import a recipe from the given source.
     *
     * @param  string|array  $source  Raw content (Cooklang text, JSON-LD array, etc.)
     * @param  Collection|null  $products  Pre-loaded products for ingredient matching (fetched from DB if null)
     */
    public function import(string|array $source, ?Collection $products = null): RecipeImportResult;
}
