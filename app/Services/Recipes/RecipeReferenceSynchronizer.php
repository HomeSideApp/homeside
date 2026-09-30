<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Support\Str;

/**
 * Persists the recipe references found in a step description, resolving
 * their targets by explicit ids or by path.
 */
final class RecipeReferenceSynchronizer
{
    public function __construct(private CooklangParser $parser) {}

    /**
     * Sync the recipe references of a step from its description.
     *
     * @param  array<int, string>  $referencedRecipeIdsByOrder
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  RecipeStep  $step  The recipe step model instance.
     */
    public function sync(Recipe $recipe, RecipeStep $step, array $referencedRecipeIdsByOrder = []): void
    {
        $step->recipeReferences()->delete();

        foreach ($this->parser->parseRecipeReferences($step->description) as $order => $reference) {
            $target = isset($referencedRecipeIdsByOrder[$order])
                ? Recipe::query()
                    ->where('owner_id', $recipe->owner_id)
                    ->find($referencedRecipeIdsByOrder[$order])
                : $this->resolve($recipe, $reference['path']);

            if ($target === null || $target->is($recipe)) {
                continue;
            }

            $step->recipeReferences()->create([
                'recipe_id' => $recipe->id,
                'referenced_recipe_id' => $target->id,
                'path' => $reference['path'],
                'quantity' => $reference['quantity'],
                'unit' => $reference['unit'],
                'order' => $order,
            ]);
        }
    }

    /**
     * Resolve the recipe referenced by a Cooklang path.
     */
    private function resolve(Recipe $recipe, string $path): ?Recipe
    {
        $normalizedPath = Str::of($path)
            ->replaceStart('./', '')
            ->replaceEnd('.cook', '')
            ->trim()
            ->toString();
        $name = Str::afterLast($normalizedPath, '/');
        $collectionPath = Str::contains($normalizedPath, '/') ? Str::beforeLast($normalizedPath, '/') : null;

        $query = Recipe::query()
            ->where('owner_id', $recipe->owner_id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($collectionPath !== null) {
            $query->whereHas('collection', fn ($collectionQuery) => $collectionQuery
                ->whereRaw('LOWER(path) = ?', [Str::lower($collectionPath)]));
        }

        return $query->first();
    }
}
