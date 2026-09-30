<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Models\User;

/**
 * Builds the plain-text catalog of recipes the user owns that could be linked
 * as a component of a recipe being generated.
 *
 * This is handed to the model as prompt context instead of exposed as a tool:
 * combining tool-calling with a json_schema response_format makes some
 * OpenAI-compatible endpoints echo the tool definition into the structured
 * payload instead of invoking it, which corrupts the generated recipe.
 *
 * Scoping is by ownership, matching both the manual editor's reference picker
 * and RecipeReferenceSynchronizer's path resolution: a reference to a recipe
 * the user does not own would not resolve when the recipe is persisted.
 */
final class RecipeReferenceCatalog
{
    /**
     * Maximum number of recipes listed, to bound the prompt size.
     */
    private const MAX_RECIPES = 200;

    /**
     * Build the catalog block for the given user, or null when they own no
     * recipes (the caller then adds nothing to the prompt).
     *
     * Every line carries the id and the exact Cooklang path so the model can
     * link a recipe without guessing how it is named or where it lives.
     */
    public function build(User $user): ?string
    {
        $recipes = Recipe::query()
            ->where('owner_id', $user->id)
            ->with('collection')
            ->orderBy('name')
            ->limit(self::MAX_RECIPES)
            ->get(['id', 'name', 'servings', 'collection_id']);

        if ($recipes->isEmpty()) {
            return null;
        }

        $lines = $recipes->map(fn (Recipe $recipe): string => sprintf(
            '- "%s"%s | id: %s | ruta: ./%s',
            $recipe->name,
            $recipe->servings !== null ? " ({$recipe->servings} raciones)" : '',
            $recipe->id,
            $this->pathFor($recipe),
        ));

        return 'Recetas que ya tiene el usuario y que puedes enlazar como componente '
            ."(no las expliques: referencia su ruta en el paso y declara el enlace en \"recipe_references\"):\n"
            .$lines->implode("\n");
    }

    /**
     * Compute the reference path of a recipe, excluding the './' prefix.
     *
     * Mirrors RecipeReferenceSynchronizer's canonical form (collection path
     * plus name) so the path written by the model resolves back to the same
     * recipe when the reference is persisted.
     */
    public function pathFor(Recipe $recipe): string
    {
        return collect([$recipe->collection?->path, $recipe->name])
            ->filter()
            ->implode('/');
    }
}
