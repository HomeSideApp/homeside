<?php

namespace App\Services\Recipes\Import;

use App\Data\Recipes\RecipeData;
use App\Data\Recipes\RecipeImportResult;
use App\Data\Recipes\RecipeImportWarning;
use App\Services\Recipes\Importer\RecipeImporter;
use App\Services\Recipes\IngredientMatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Imports a JSON-LD recipe object into structured RecipeData.
 */
class JsonLdRecipeImporter implements RecipeImporter
{
    private DurationParser $durationParser;

    private IngredientTextParser $ingredientParser;

    public function __construct(
        DurationParser $durationParser,
        IngredientTextParser $ingredientParser,
        private IngredientMatcher $matcher,
    ) {
        $this->durationParser = $durationParser;
        $this->ingredientParser = $ingredientParser;
    }

    /**
     * Import a JSON-LD recipe array into RecipeData.
     *
     * @param  string|array  $source  JSON-LD recipe array
     * @param  Collection<int, Product>|null  $products  Pre-loaded products for matching
     * @return RecipeImportResult The RecipeImportResult value.
     */
    public function import(string|array $source, ?Collection $products = null): RecipeImportResult
    {
        return $this->importJsonLd((array) $source, $products);
    }

    /**
     * Import a JSON-LD recipe array into RecipeData.
     *
     * @param  array<string, mixed>  $jsonLd
     * @param  Collection<int, Product>|null  $products  Pre-loaded products for matching
     * @return RecipeImportResult The RecipeImportResult value.
     */
    private function importJsonLd(array $jsonLd, ?Collection $products = null): RecipeImportResult
    {
        $warnings = [];

        // Extract name
        $name = $jsonLd['name'] ?? 'Receta importada';
        if (! is_string($name)) {
            $warnings[] = new RecipeImportWarning(
                code: 'invalid_name',
                message: 'Nombre de receta inválido.',
                level: 'WARNING',
            );
        }

        // Extract description
        $description = isset($jsonLd['description']) && is_string($jsonLd['description'])
            ? $jsonLd['description']
            : null;

        // Extract author
        $author = isset($jsonLd['author'])
            ? $this->extractAuthor($jsonLd['author'])
            : null;

        // Extract image
        $coverImageUrl = $this->extractImage($jsonLd['image'] ?? null);

        // Extract times (ISO 8601 durations → seconds)
        $prepTimeSeconds = $this->parseDuration($jsonLd['prepTime'] ?? null, 'prep_time', $warnings);
        $cookTimeSeconds = $this->parseDuration($jsonLd['cookTime'] ?? null, 'cook_time', $warnings);
        $totalTimeSeconds = $this->parseDuration($jsonLd['totalTime'] ?? null, 'total_time', $warnings);

        // Extract yield
        $servings = null;
        $yieldText = null;
        if (isset($jsonLd['recipeYield'])) {
            if (is_int($jsonLd['recipeYield']) || is_float($jsonLd['recipeYield'])) {
                $servings = (int) $jsonLd['recipeYield'];
            } elseif (is_string($jsonLd['recipeYield'])) {
                $yieldText = $jsonLd['recipeYield'];
                if (preg_match('/(\d+)/', $jsonLd['recipeYield'], $m)) {
                    $servings = (int) $m[1];
                }
            }
        }

        // Extract ingredients
        $ingredients = [];
        if (isset($jsonLd['recipeIngredient'])) {
            $ingredientList = is_array($jsonLd['recipeIngredient'])
                ? $jsonLd['recipeIngredient']
                : [$jsonLd['recipeIngredient']];
            foreach ($ingredientList as $ingredient) {
                if (is_string($ingredient)) {
                    $ingredients[] = $this->ingredientParser->parse($ingredient);
                }
            }
        }

        // Extract instructions (HowToStep, HowToSection, or Text)
        $sections = [];
        $steps = [];
        if (isset($jsonLd['recipeInstructions'])) {
            $this->extractInstructions($jsonLd['recipeInstructions'], $sections, $steps);
        }

        // Extract keywords
        $tags = [];
        if (isset($jsonLd['keywords'])) {
            if (is_string($jsonLd['keywords'])) {
                $tags = array_filter(array_map('trim', explode(',', $jsonLd['keywords'])));
            } elseif (is_array($jsonLd['keywords'])) {
                $tags = array_values(array_filter(array_map('strval', $jsonLd['keywords'])));
            }
        }

        // Build RecipeData via fromArray
        $data = [
            'name' => $name,
            'description' => $description,
            'servings' => $servings,
            'yield_text' => $yieldText,
            'prep_time_seconds' => $prepTimeSeconds,
            'cook_time_seconds' => $cookTimeSeconds,
            'total_time_seconds' => $totalTimeSeconds,
            'difficulty' => null,
            'cuisine' => isset($jsonLd['recipeCuisine']) && is_string($jsonLd['recipeCuisine'])
                ? $jsonLd['recipeCuisine']
                : null,
            'locale' => isset($jsonLd['inLanguage']) && is_string($jsonLd['inLanguage'])
                ? $jsonLd['inLanguage']
                : null,
            'author' => $author,
            'source_url' => isset($jsonLd['url']) && is_string($jsonLd['url'])
                ? $jsonLd['url']
                : null,
            'source_name' => null,
            'source_type' => 'jsonld',
            'cover_image_path' => $coverImageUrl,
            'cooking_method' => isset($jsonLd['cookingMethod']) && is_string($jsonLd['cookingMethod'])
                ? $jsonLd['cookingMethod']
                : null,
            'recipe_category' => isset($jsonLd['recipeCategory']) && is_string($jsonLd['recipeCategory'])
                ? $jsonLd['recipeCategory']
                : null,
            'suitable_for_diet' => isset($jsonLd['suitableForDiet'])
                ? array_values(array_filter(array_map('strval', Arr::wrap($jsonLd['suitableForDiet']))))
                : null,
            'tags' => $tags,
            'sections' => $sections,
            'ingredients' => array_map(
                fn (int $index, array $ing) => [
                    'id' => null,
                    'client_id' => null,
                    'name' => $ing['name'] ?: ($ing['original_text'] ?? ''),
                    'product_id' => null,
                    'quantity' => $ing['quantity'] ?? null,
                    'quantity_text' => $ing['quantity_text'] ?? null,
                    'unit' => $ing['unit'] ?? null,
                    'preparation' => $ing['preparation'] ?? null,
                    'notes' => $ing['notes'] ?? null,
                    'optional' => false,
                    'section_id' => null,
                    'order' => $index + 1,
                ],
                array_keys($ingredients),
                $ingredients,
            ),
            'steps' => $steps,
            'cookware' => [],
            'supplies' => [],
            'notes' => null,
        ];

        // Match ingredients against the product catalog
        $products ??= $this->matcher->productsForMatching();
        $candidates = $this->matcher->matchMany(
            array_map(fn (array $ing): string => $ing['name'] ?? '', $data['ingredients']),
            $products,
        );

        return new RecipeImportResult(
            recipe: RecipeData::fromArray($data),
            warnings: $warnings,
            source: 'jsonld',
            candidates: $candidates,
        );
    }

    /**
     * Parse an ISO 8601 duration, adding warnings on failure.
     */
    /**
     * @param  array<int, RecipeImportWarning>  $warnings
     */
    private function parseDuration(?string $value, string $code, array &$warnings): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->durationParser->parseIso8601($value);
        } catch (\InvalidArgumentException) {
            $warnings[] = new RecipeImportWarning(
                code: "invalid_{$code}",
                message: "Tiempo no válido: {$value}",
                level: 'WARNING',
            );

            return null;
        }
    }

    /**
     * Extract author from JSON-LD author field.
     */
    private function extractAuthor(mixed $author): ?string
    {
        if (is_string($author)) {
            return $author;
        }

        if (is_array($author)) {
            if (isset($author['name']) && is_string($author['name'])) {
                return $author['name'];
            }

            if (isset($author['@type']) && is_string($author['@type']) &&
                str_contains(strtolower($author['@type']), 'organization')) {
                return $author['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Extract image URL from JSON-LD image field.
     */
    private function extractImage(mixed $image): ?string
    {
        if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        if (is_array($image) && ! empty($image)) {
            foreach ($image as $img) {
                if (is_string($img) && filter_var($img, FILTER_VALIDATE_URL)) {
                    return $img;
                }

                if (is_array($img) && isset($img['contentUrl']) && is_string($img['contentUrl'])) {
                    return $img['contentUrl'];
                }
            }
        }

        return null;
    }

    /**
     * Extract recipeInstructions into sections and steps arrays.
     *
     * @param  string|array<string, mixed>|array<int, string|array<string, mixed>>  $instructions
     * @param  array<int, array{id: null, client_id: string|null, name: string, order: int}>  $sections
     * @param  array<int, array{id: null, client_id: null, description: string, image_path: null, image_url?: string|null, section_id: string|null, ingredients: array<string>, cookware: array<int, mixed>, timers: array<int, mixed>, order: int}>  $steps
     */
    private function extractInstructions(mixed $instructions, array &$sections, array &$steps): void
    {
        if (is_string($instructions)) {
            $lines = array_filter(explode("\n", $instructions));
            foreach ($lines as $line) {
                $steps[] = [
                    'id' => null,
                    'client_id' => null,
                    'description' => trim($line),
                    'image_path' => null,
                    'section_id' => null,
                    'ingredients' => [],
                    'cookware' => [],
                    'timers' => [],
                    'order' => count($steps),
                ];
            }

            return;
        }

        foreach ($instructions as $inst) {
            if (is_string($inst)) {
                $steps[] = [
                    'id' => null,
                    'client_id' => null,
                    'description' => $inst,
                    'image_path' => null,
                    'section_id' => null,
                    'ingredients' => [],
                    'cookware' => [],
                    'timers' => [],
                    'order' => count($steps) + 1,
                ];

                continue;
            }

            $type = strtolower($inst['@type'] ?? '');

            if (str_contains($type, 'section')) {
                // HowToSection
                $sectionName = is_string($inst['name'] ?? null) ? $inst['name'] : 'Sección';
                $clientId = 'section_'.count($sections);
                $sections[] = [
                    'id' => null,
                    'client_id' => $clientId,
                    'name' => $sectionName,
                    'order' => count($sections) + 1,
                ];

                $stepsList = $inst['itemListElement'] ?? $inst['step'] ?? [];
                foreach (is_array($stepsList) ? $stepsList : [] as $step) {
                    $steps[] = $this->extractStepData($step, $clientId, count($steps) + 1);
                }
            } elseif (str_contains($type, 'step')) {
                // HowToStep
                $steps[] = $this->extractStepData($inst, null, count($steps) + 1);
            }
        }
    }

    /**
     * Extract a single HowToStep into a step array.
     *
     * @param  array<string, mixed>|string  $step
     * @return array{id: null, client_id: null, description: string, image_path: null, image_url: ?string, section_id: ?string, ingredients: array<string>, cookware: array<int, mixed>, timers: array<int, mixed>, order: int}
     */
    private function extractStepData(array|string $step, ?string $sectionId = null, int $order = 0): array
    {
        if (is_string($step)) {
            return [
                'id' => null,
                'client_id' => null,
                'description' => $step,
                'image_path' => null,
                'image_url' => null,
                'section_id' => $sectionId,
                'ingredients' => [],
                'cookware' => [],
                'timers' => [],
                'order' => $order,
            ];
        }

        $description = '';

        if (isset($step['text']) && is_string($step['text'])) {
            $description = $step['text'];
        } elseif (isset($step['name']) && is_string($step['name'])) {
            $description = $step['name'];
        }

        $imageUrl = $this->extractImage($step['image'] ?? null);

        return [
            'id' => null,
            'client_id' => null,
            'description' => $description,
            'image_path' => null,
            'image_url' => $imageUrl,
            'section_id' => $sectionId,
            'ingredients' => [],
            'cookware' => [],
            'timers' => [],
            'order' => $order,
        ];
    }
}
