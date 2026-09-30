<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Models\RecipeReference;
use App\Models\RecipeStep;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rewrites in step descriptions the recipe reference paths to their
 * canonical form and re-syncs the references.
 */
final class ReconcileRecipeReferencePaths
{
    public function __construct(
        private CooklangParser $parser,
        private RecipeReferenceSynchronizer $synchronizer,
    ) {}

    /**
     * @param  string|null  $referencedRecipeId  When set, only steps referencing this recipe are processed
     */
    public function execute(?string $referencedRecipeId = null): void
    {
        RecipeStep::query()
            ->when(
                $referencedRecipeId,
                fn (Builder $query, string $recipeId) => $query->whereHas(
                    'recipeReferences',
                    fn (Builder $referenceQuery) => $referenceQuery->where('referenced_recipe_id', $recipeId),
                ),
                fn (Builder $query) => $query->where(function (Builder $referenceSyntaxQuery) {
                    $referenceSyntaxQuery
                        ->where('description', 'like', '%@./%')
                        ->orWhere('description', 'like', '%@../%');
                }),
            )
            ->with(['recipe', 'recipeReferences.referencedRecipe.collection'])
            ->chunkById(100, fn ($steps) => $steps->each(fn (RecipeStep $step) => $this->reconcileStep($step)));
    }

    /**
     * Rewrite the reference paths of a single step description.
     */
    private function reconcileStep(RecipeStep $step): void
    {
        $recipe = $step->recipe;

        if ($recipe === null) {
            return;
        }

        $segments = $this->parser->parseRecipeReferences($step->description);
        $references = $step->recipeReferences->keyBy('order');
        $description = $step->description;

        foreach (array_reverse($segments, true) as $order => $segment) {
            /** @var RecipeReference|null $reference */
            $reference = $references->get($order);
            $target = $reference?->referencedRecipe;

            if ($target === null) {
                continue;
            }

            $canonicalPath = $this->canonicalPath($target);
            $replacement = '@'.$canonicalPath.'{'.$this->quantityContent($segment['quantity'], $segment['unit']).'}';
            $description = substr_replace(
                $description,
                $replacement,
                $segment['start'],
                $segment['end'] - $segment['start'],
            );
        }

        if ($description !== $step->description) {
            $step->update(['description' => $description]);
        }

        $this->synchronizer->sync($recipe, $step);
    }

    /**
     * Compute the canonical reference path of a recipe.
     */
    private function canonicalPath(Recipe $recipe): string
    {
        return './'.collect([$recipe->collection?->path, $recipe->name])
            ->filter()
            ->implode('/');
    }

    /**
     * Format a quantity and unit for the Cooklang reference replacement.
     */
    private function quantityContent(?float $quantity, ?string $unit): string
    {
        if ($quantity === null) {
            return '';
        }

        $formattedQuantity = $quantity === (float) (int) $quantity
            ? (string) (int) $quantity
            : rtrim(rtrim((string) $quantity, '0'), '.');

        return $formattedQuantity.($unit !== null ? '%'.$unit : '');
    }
}
