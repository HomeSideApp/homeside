<?php

namespace App\Services\Recipes;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Turns the recipe references proposed by the AI into per-step, ordered target
 * ids that RecipeReferenceSynchronizer can persist without relying on a name
 * match performed at persistence time.
 *
 * The model receives the exact paths of the user's recipes in the prompt and
 * echoes them back, but nothing it returns is trusted: a target is only
 * accepted when it resolves to a recipe the user owns. Ownership (not full
 * visibility) is the criterion on purpose — both RecipeReferenceSynchronizer's
 * path resolution and the manual editor's reference picker resolve targets
 * within the owner's recipes, so accepting a household-shared recipe here
 * would produce a link that silently disappears when persisted.
 */
final class RecipeReferenceTargetResolver
{
    public function __construct(private CooklangParser $parser) {}

    /**
     * Enrich every step of a normalized recipe with the ordered ids of the
     * recipes it references.
     *
     * @param  array<string, mixed>  $recipe  Normalized recipe (steps + recipe_references).
     * @param  User  $user  The user the references must resolve to.
     * @return array<string, mixed> The recipe with `reference_recipe_ids` on each step.
     */
    public function applyToSteps(array $recipe, User $user): array
    {
        $steps = is_array($recipe['steps'] ?? null) ? $recipe['steps'] : [];

        if ($steps === []) {
            return $recipe;
        }

        // The model does not always fill `recipe_references`; fall back to the
        // paths it embedded in the step descriptions.
        $declared = $recipe['recipe_references'] ?? [];

        if (! is_array($declared) || $declared === []) {
            $declared = $this->pathsInDescriptions($steps);
        }

        $idByPath = $this->idByPath($declared, $user);

        $recipe['steps'] = array_map(function ($step) use ($idByPath): array {
            $step = is_array($step) ? $step : [];

            $description = is_string($step['description'] ?? null) ? $step['description'] : '';

            $step['reference_recipe_ids'] = $this->targetsForDescription($description, $idByPath);

            return $step;
        }, $steps);

        return $recipe;
    }

    /**
     * Build the target ids for one step description, keyed by the position of
     * each Cooklang reference.
     *
     * Keys are preserved (never re-indexed) because
     * RecipeReferenceSynchronizer matches the map by the reference position:
     * a hole left by an unresolvable reference must not shift the entries that
     * follow it. When no explicit path map is supplied, the paths embedded in
     * the description are resolved against the user's own recipes, so a link
     * survives even if the model omitted the `recipe_references` array.
     *
     * @param  string  $description  The step description with Cooklang references.
     * @param  array<string, string>  $idByPath  Normalized path to recipe id map.
     * @return array<int, string> Reference position to resolved recipe id.
     */
    public function targetsForDescription(string $description, array $idByPath = []): array
    {
        $targets = [];

        foreach ($this->parser->parseRecipeReferences($description) as $order => $reference) {
            $normalized = $this->normalizePath($reference['path']);

            $recipeId = $idByPath[$normalized] ?? null;

            if ($recipeId === null) {
                continue;
            }

            $targets[$order] = $recipeId;
        }

        return $targets;
    }

    /**
     * Collect the reference paths embedded in the step descriptions.
     *
     * @param  array<int, mixed>  $steps  The normalized steps.
     * @return list<array{path: string, recipe_id: null}>
     */
    private function pathsInDescriptions(array $steps): array
    {
        $declared = [];

        foreach ($steps as $step) {
            $description = is_array($step) && is_string($step['description'] ?? null)
                ? $step['description']
                : '';

            if ($description === '') {
                continue;
            }

            foreach ($this->parser->parseRecipeReferences($description) as $reference) {
                $declared[] = ['path' => $reference['path'], 'recipe_id' => null];
            }
        }

        return $declared;
    }

    /**
     * Map a normalized reference path to a recipe id the user owns.
     *
     * `recipe_id` from the model is honoured only when the user owns that
     * recipe; otherwise the path is resolved against the user's recipes, which
     * is what RecipeReferenceSynchronizer would do anyway.
     *
     * @param  mixed  $references  The `recipe_references` array of the agent response.
     * @param  User  $user  The user the references must resolve to.
     * @return array<string, string> Normalized path to recipe id.
     */
    public function idByPath(mixed $references, User $user): array
    {
        if (! is_array($references) || $references === []) {
            return [];
        }

        // Paths the model declared, kept alongside its optional recipe_id.
        $declared = [];

        foreach ($references as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $path = $reference['path'] ?? null;

            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $recipeId = $reference['recipe_id'] ?? null;

            $declared[$this->normalizePath($path)] = is_string($recipeId) && $recipeId !== ''
                ? $recipeId
                : null;
        }

        if ($declared === []) {
            return [];
        }

        $owned = $this->ownedRecipesByPath($user, array_keys($declared));

        $resolved = [];

        foreach ($declared as $path => $recipeId) {
            if ($recipeId !== null && ($owned[$recipeId] ?? null) === $path) {
                $resolved[$path] = $recipeId;

                continue;
            }

            $ownedId = array_search($path, $owned, true);

            if ($ownedId !== false) {
                $resolved[$path] = (string) $ownedId;
            }
        }

        return $resolved;
    }

    /**
     * Build the normalized path to id map of the recipes the user owns.
     *
     * @param  User  $user  The owner.
     * @param  array<int, string>  $wantedPaths  Only recipes matching these paths are loaded.
     * @return array<string, string> Recipe id to normalized path.
     */
    private function ownedRecipesByPath(User $user, array $wantedPaths): array
    {
        $names = [];

        foreach ($wantedPaths as $path) {
            // The reference path ends with the recipe name; the parent segment,
            // when present, is the recipe collection path.
            $names[] = trim(Str::afterLast($path, '/'));
        }

        $names = array_values(array_unique(array_filter($names)));

        if ($names === []) {
            return [];
        }

        return Recipe::query()
            ->where('owner_id', $user->id)
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhereRaw('LOWER(name) = ?', [Str::lower($name)]);
                }
            })
            ->with('collection')
            ->get(['id', 'name', 'collection_id'])
            ->mapWithKeys(function (Recipe $recipe): array {
                $path = $this->normalizePath(collect([$recipe->collection?->path, $recipe->name])
                    ->filter()
                    ->implode('/'));

                return [(string) $recipe->id => $path];
            })
            ->all();
    }

    /**
     * Normalize a Cooklang reference path for comparison.
     *
     * Mirrors RecipeReferenceSynchronizer's normalization so the path written
     * by the model and the canonical path of a stored recipe compare equal.
     *
     * @param  string  $path  The raw reference path.
     * @return string The normalized, lowercased path.
     */
    private function normalizePath(string $path): string
    {
        return Str::of($path)
            ->replaceStart('./', '')
            ->replaceEnd('.cook', '')
            ->trim()
            ->lower()
            ->toString();
    }
}
