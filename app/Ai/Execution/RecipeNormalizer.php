<?php

declare(strict_types=1);

namespace App\Ai\Execution;

use App\Models\Product;
use App\Services\Recipes\IngredientMatcher;
use Illuminate\Support\Collection;

/**
 * Normalizes and parses the JSON response of the recipe agent, and matches
 * ingredients with catalog products.
 *
 * Extracted from RecipeAiService to be reused by RecipeAiController.
 */
final class RecipeNormalizer
{
    /**
     * Check that the response contains the minimum needed to edit a recipe.
     *
     * @param  array<string, mixed>  $recipe
     */
    public static function hasRequiredContent(array $recipe): bool
    {
        return is_string($recipe['name'] ?? null)
            && filled($recipe['name'])
            && is_array($recipe['ingredients'] ?? null)
            && $recipe['ingredients'] !== []
            && is_array($recipe['steps'] ?? null)
            && $recipe['steps'] !== [];
    }

    /**
     * Collect the validation errors of a parsed recipe response.
     *
     * @param  array<string, mixed>  $recipe
     * @return list<string>
     */
    public static function validationErrors(array $recipe): array
    {
        $errors = [];

        if (! self::hasRequiredContent($recipe)) {
            $errors[] = 'La receta debe incluir un nombre no vacío, ingredientes y pasos.';

            return $errors;
        }

        $ingredientIds = self::clientIds($recipe['ingredients']);
        $cookwareIds = self::clientIds(is_array($recipe['cookware'] ?? null) ? $recipe['cookware'] : []);

        $rawReferences = $recipe['recipe_references'] ?? null;

        if (is_array($rawReferences)) {
            foreach ($rawReferences as $index => $reference) {
                $path = is_array($reference) ? ($reference['path'] ?? null) : null;

                if (! is_string($path) || trim($path) === '') {
                    $errors[] = sprintf('La referencia a otra receta %d no indica una ruta válida.', $index + 1);
                }
            }
        }

        foreach ($recipe['steps'] as $index => $step) {
            if (! is_array($step)) {
                $errors[] = sprintf('El paso %d no tiene una estructura válida.', $index + 1);

                continue;
            }

            $stepIngredients = is_array($step['ingredients'] ?? null) ? $step['ingredients'] : [];
            $stepCookware = is_array($step['cookware'] ?? null) ? $step['cookware'] : [];

            foreach ($stepIngredients as $ingredientId) {
                if (! is_string($ingredientId) || ! in_array($ingredientId, $ingredientIds, true)) {
                    $errors[] = sprintf('El paso %d referencia un client_id de ingrediente inexistente.', $index + 1);
                }
            }

            foreach ($stepCookware as $cookwareId) {
                if (! is_string($cookwareId) || ! in_array($cookwareId, $cookwareIds, true)) {
                    $errors[] = sprintf('El paso %d referencia un client_id de utensilio inexistente.', $index + 1);
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Extract the client ids of a list of items.
     *
     * @param  array<int, mixed>  $items
     * @return list<string>
     */
    private static function clientIds(array $items): array
    {
        $clientIds = [];

        foreach ($items as $item) {
            if (is_array($item) && is_string($item['client_id'] ?? null) && filled($item['client_id'])) {
                $clientIds[] = $item['client_id'];
            }
        }

        return $clientIds;
    }

    /**
     * Parse JSON from the agent response (direct, markdown code block, or
     * embedded).
     *
     * @return array<string, mixed>|null
     */
    public static function parseJsonFromResponse(string $content): ?array
    {
        // 1. Intento directo
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 2. Extraer de markdown code block: ```json ... ```
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Buscar primer { y último } para extraer JSON embebido
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Collect the non-blocking consistency issues of a linked recipe.
     *
     * Declaring `recipe_references` without embedding the Cooklang reference in
     * any step produces a link the renderer cannot show and the synchronizer
     * cannot persist. Unlike validationErrors(), this never aborts a
     * generation: it only asks the caller for one more attempt, and a recipe
     * that still lacks the embedded reference is accepted as-is.
     *
     * @param  array<string, mixed>  $recipe
     * @return list<string>
     */
    public static function referenceConsistencyWarnings(array $recipe): array
    {
        $references = $recipe['recipe_references'] ?? null;

        if (! is_array($references) || $references === []) {
            return [];
        }

        $steps = is_array($recipe['steps'] ?? null) ? $recipe['steps'] : [];

        foreach ($steps as $step) {
            $description = is_array($step) && is_string($step['description'] ?? null)
                ? $step['description']
                : '';

            if ($description !== '' && preg_match('/@\.{1,2}\//', $description) === 1) {
                return [];
            }
        }

        return [
            'Has declarado "recipe_references" pero ningún paso contiene la referencia Cooklang. '
            .'Escribe el componente en el paso como @./ruta/Receta{cantidad%unidad} y no expliques su elaboración.',
        ];
    }

    /**
     * Extract the recipe references the model linked instead of re-explaining.
     *
     * Each entry maps a Cooklang path embedded in a step description to the
     * id of the recipe it refers to, so persistence can resolve the target by
     * id instead of relying on a name match.
     *
     * @param  array<string, mixed>  $recipe
     * @return list<array{path: string, recipe_id: string|null}>
     */
    public static function recipeReferences(array $recipe): array
    {
        $references = $recipe['recipe_references'] ?? null;

        if (! is_array($references)) {
            return [];
        }

        $normalized = [];

        foreach ($references as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $path = $reference['path'] ?? null;

            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $recipeId = $reference['recipe_id'] ?? null;

            $normalized[] = [
                'path' => trim($path),
                'recipe_id' => is_string($recipeId) && $recipeId !== '' ? $recipeId : null,
            ];
        }

        return $normalized;
    }

    /**
     * Normalize the structure of a parsed recipe to the form format
     * (RecipeForm): name, prep_time_seconds, steps[].description, etc.
     *
     * Tolerant to both schemas: the legacy one (title/prep_time/steps[].instruction)
     * and the new structured output (name/prep_time_seconds/steps[].description).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeRecipe(array $data): array
    {
        return [
            'name' => $data['name'] ?? $data['title'] ?? 'Receta sin título',
            'description' => $data['description'] ?? null,
            'servings' => $data['servings'] ?? null,
            'yield_text' => $data['yield_text'] ?? null,
            'prep_time_seconds' => $data['prep_time_seconds'] ?? $data['prep_time'] ?? null,
            'cook_time_seconds' => $data['cook_time_seconds'] ?? $data['cook_time'] ?? null,
            'total_time_seconds' => $data['total_time_seconds'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'cuisine' => $data['cuisine'] ?? null,
            'cooking_method' => $data['cooking_method'] ?? null,
            'recipe_category' => $data['recipe_category'] ?? null,
            'author' => $data['author'] ?? null,
            'notes' => $data['notes'] ?? null,
            'tags' => $data['tags'] ?? [],
            'sections' => $data['sections'] ?? [],
            'ingredients' => array_map(function ($ing) {
                $ing = is_array($ing) ? $ing : ['name' => is_string($ing) ? $ing : ''];

                return [
                    'client_id' => $ing['client_id'] ?? null,
                    'name' => $ing['name'] ?? $ing['ingredient'] ?? '',
                    'quantity' => $ing['quantity'] ?? null,
                    'unit' => $ing['unit'] ?? null,
                    'preparation' => $ing['preparation'] ?? null,
                    'notes' => $ing['notes'] ?? null,
                    'optional' => $ing['optional'] ?? false,
                    'order' => $ing['order'] ?? null,
                    'section_id' => $ing['section_id'] ?? null,
                    'product_id' => $ing['product_id'] ?? null,
                ];
            }, $data['ingredients'] ?? []),
            'steps' => array_map(function ($step) {
                $step = is_array($step) ? $step : ['description' => is_string($step) ? $step : ''];

                return [
                    'client_id' => $step['client_id'] ?? null,
                    'description' => $step['description'] ?? $step['instruction'] ?? $step['text'] ?? '',
                    'order' => $step['order'] ?? $step['step_number'] ?? null,
                    'section_id' => $step['section_id'] ?? null,
                    'ingredients' => $step['ingredients'] ?? [],
                    'cookware' => $step['cookware'] ?? [],
                    'timers' => $step['timers'] ?? [],
                ];
            }, $data['steps'] ?? []),
            'recipe_references' => self::recipeReferences($data),
            'cookware' => array_map(function ($cw) {
                $cw = is_array($cw) ? $cw : ['name' => is_string($cw) ? $cw : ''];

                return [
                    'client_id' => $cw['client_id'] ?? null,
                    'name' => $cw['name'] ?? '',
                    'type' => $cw['type'] ?? 'tool',
                    'quantity' => $cw['quantity'] ?? null,
                    'unit' => $cw['unit'] ?? null,
                    'section_id' => $cw['section_id'] ?? null,
                    'order' => $cw['order'] ?? null,
                ];
            }, $data['cookware'] ?? []),
        ];
    }

    /**
     * Match ingredients with catalog products by name.
     *
     * Delegates to IngredientMatcher so the whole app shares one matching
     * algorithm (importers and AI normalization behave identically).
     *
     * @param  array<int, array<string, mixed>>  $ingredients
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public static function matchIngredientsToProducts(array $ingredients, Collection $products): array
    {
        $matcher = new IngredientMatcher;
        $productsById = $products->keyBy(fn (Product $product): string => (string) $product->getKey());
        $names = array_map(
            fn (array $ingredient): string => is_string($ingredient['name'] ?? null) ? $ingredient['name'] : '',
            $ingredients,
        );
        $candidates = collect($matcher->matchMany($names, $products, minScore: 1.0))
            ->keyBy('ingredient');

        return array_map(function (array $ingredient) use ($productsById, $candidates) {
            $productId = $ingredient['product_id'] ?? null;

            if (is_string($productId) && $productsById->has($productId)) {
                return $ingredient;
            }

            $ingredient['product_id'] = $candidates[$ingredient['name'] ?? '']['product_id'] ?? null;

            return $ingredient;
        }, $ingredients);
    }
}
