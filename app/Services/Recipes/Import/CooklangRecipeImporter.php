<?php

namespace App\Services\Recipes\Import;

use App\Data\Recipes\RecipeData;
use App\Data\Recipes\RecipeImportResult;
use App\Services\Recipes\CooklangParser;
use App\Services\Recipes\Importer\RecipeImporter;
use App\Services\Recipes\IngredientMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @phpstan-type StepToken array{type: 'step', content: string, ingredient_refs: array<string>, timer_refs: array<array{name: string|null, duration_raw: string}>, cookware_refs: array<string>}
 * @phpstan-type SimpleToken array{type: 'section'|'note', content: string}
 */
/**
 * Imports Cooklang recipe content into structured RecipeData.
 */
class CooklangRecipeImporter implements RecipeImporter
{
    private DurationParser $durationParser;

    private IngredientTextParser $ingredientParser;

    /** @var array<string, array{name: string, quantity: ?float, unit: ?string, preparation: ?string}> */
    private array $ingredientsCache = [];

    /** @var array<string, array{name: string, quantity: ?float, unit: ?string}> */
    private array $cookwareCache = [];

    public function __construct(
        DurationParser $durationParser,
        IngredientTextParser $ingredientParser,
        private CooklangParser $cooklangParser,
        private IngredientMatcher $matcher,
    ) {
        $this->durationParser = $durationParser;
        $this->ingredientParser = $ingredientParser;
    }

    /**
     * Import cooklang content into RecipeData.
     *
     * @param  string|array  $content  Raw cooklang content (text or file upload)
     * @param  Collection<int, Product>|null  $products  Pre-loaded products for matching (fetched from DB if null)
     * @return RecipeImportResult The RecipeImportResult value.
     */
    public function import(string|array $content, ?Collection $products = null): RecipeImportResult
    {
        $warnings = [];
        $tags = [];

        // Parse YAML front matter
        $frontMatter = [];
        $body = $content;

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $content, $matches)) {
            $yamlContent = $matches[1];
            $body = $matches[2];

            $frontMatter = $this->parseYamlFrontMatter($yamlContent);
            if (isset($frontMatter['tags']) && is_array($frontMatter['tags'])) {
                $tags = $frontMatter['tags'];
            } elseif (isset($frontMatter['tags']) && is_string($frontMatter['tags'])) {
                $tags = array_filter(array_map('trim', explode(',', $frontMatter['tags'])));
            }
        }

        // Tokenize cooklang content
        $tokens = $this->tokenize($body);

        // Map md5 ref IDs to proper UUIDs
        $uuidMap = [];
        foreach ($this->ingredientsCache as $refId => $_) {
            $uuidMap[$refId] = (string) Str::uuid();
        }
        foreach ($this->cookwareCache as $refId => $_) {
            $uuidMap[$refId] = (string) Str::uuid();
        }

        // Build RecipeData from tokens
        $sections = [];
        $steps = [];
        $currentSectionId = null;
        $notes = null;

        foreach ($tokens as $token) {
            if ($token['type'] === 'section') {
                $clientId = (string) Str::uuid();
                $sections[] = [
                    'id' => null,
                    'client_id' => $clientId,
                    'name' => $token['content'],
                    'order' => count($sections) + 1,
                ];
                $currentSectionId = $clientId;
            } elseif ($token['type'] === 'step') {
                $timers = [];
                $timerOrder = 1;
                foreach ($token['timer_refs'] as $t) {
                    $timers[] = [
                        'id' => null,
                        'client_id' => (string) Str::uuid(),
                        'name' => $t['name'] ?? null,
                        'duration_seconds' => $this->durationParser->parseHuman($t['duration_raw']),
                        'order' => $timerOrder++,
                    ];
                }

                $steps[] = [
                    'description' => $token['content'],
                    'image_path' => null,
                    'section_id' => $currentSectionId,
                    'ingredients' => array_map(fn (string $ref) => $uuidMap[$ref] ?? $ref, $token['ingredient_refs']),
                    'cookware' => array_map(fn (string $ref) => $uuidMap[$ref] ?? $ref, $token['cookware_refs']),
                    'timers' => $timers,
                    'order' => count($steps) + 1,
                ];
            } elseif ($token['type'] === 'note') {
                $notes = $token['content'];
            }
        }

        // Build ingredients array from cache
        $ingredients = [];
        $ingredientOrder = 1;
        foreach ($this->ingredientsCache as $refId => $ing) {
            $parsed = $this->ingredientParser->parse($ing['name']);
            $quantity = $ing['quantity'] ?? $parsed['quantity'];
            $unit = $ing['unit'] ?? $parsed['unit'];

            $ingredients[] = [
                'id' => null,
                'client_id' => $uuidMap[$refId],
                'name' => $parsed['name'] !== '' ? $parsed['name'] : $ing['name'],
                'product_id' => null,
                'quantity' => $quantity,
                'quantity_text' => $parsed['quantity_text'] ?? null,
                'unit' => $unit,
                'preparation' => $ing['preparation'] ?? $parsed['preparation'] ?? null,
                'notes' => $parsed['notes'] ?? null,
                'optional' => false,
                'section_id' => null,
                'order' => $ingredientOrder++,
            ];
        }

        // Build cookware array from cache
        $cookware = [];
        $cookwareOrder = 1;
        foreach ($this->cookwareCache as $refId => $cw) {
            $cookware[] = [
                'id' => null,
                'client_id' => $uuidMap[$refId],
                'name' => $cw['name'],
                'type' => 'tool',
                'quantity' => $cw['quantity'] !== null ? (int) $cw['quantity'] : null,
                'quantity_text' => null,
                'unit' => $cw['unit'],
                'section_id' => null,
                'order' => $cookwareOrder++,
            ];
        }

        // Build RecipeData
        $data = [
            'name' => $frontMatter['title'] ?? 'Receta importada',
            'description' => $frontMatter['description'] ?? null,
            'servings' => isset($frontMatter['servings']) ? (int) $frontMatter['servings'] : null,
            'yield_text' => null,
            'prep_time_seconds' => isset($frontMatter['prep time']) ? $this->durationParser->parseHuman($frontMatter['prep time']) : null,
            'cook_time_seconds' => isset($frontMatter['cook time']) ? $this->durationParser->parseHuman($frontMatter['cook time']) : null,
            'total_time_seconds' => null,
            'difficulty' => $frontMatter['difficulty'] ?? null,
            'cuisine' => $frontMatter['cuisine'] ?? null,
            'locale' => $frontMatter['locale'] ?? null,
            'author' => $frontMatter['author'] ?? null,
            'source_url' => $frontMatter['source'] ?? null,
            'source_name' => null,
            'source_type' => 'cooklang',
            'cover_image_path' => null,
            'cooking_method' => null,
            'recipe_category' => null,
            'collection_path' => $frontMatter['collection'] ?? null,
            'suitable_for_diet' => null,
            'tags' => array_values($tags),
            'sections' => $sections,
            'ingredients' => $ingredients,
            'steps' => $steps,
            'cookware' => $cookware,
            'supplies' => [],
            'notes' => $notes,
        ];

        // Match ingredients against the product catalog
        $products ??= $this->matcher->productsForMatching();
        $candidates = $this->matcher->matchMany(
            array_map(fn (array $ing): string => $ing['name'] ?? '', $ingredients),
            $products,
        );

        return new RecipeImportResult(
            recipe: RecipeData::fromArray($data),
            warnings: $warnings,
            source: 'cooklang',
            candidates: $candidates,
        );
    }

    /**
     * Parse YAML front matter (basic parser).
     *
     * @return array<string, mixed>
     */
    private function parseYamlFrontMatter(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentArray = [];

        foreach ($lines as $line) {
            if (preg_match('/^  - (.+)$/', $line, $matches)) {
                // Array item
                if ($currentKey) {
                    $currentArray[] = trim($matches[1]);
                }
            } elseif (preg_match('/^([a-z_ ]+):\s*(.+)$/', $line, $matches)) {
                // Save previous array
                if ($currentKey && ! empty($currentArray)) {
                    $result[$currentKey] = $currentArray;
                }

                $currentKey = trim($matches[1]);
                $value = trim($matches[2]);

                if ($value === '') {
                    $currentArray = [];
                } else {
                    // Remove quotes
                    if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                        (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                        $value = substr($value, 1, -1);
                    }

                    // Handle inline arrays: [item1, item2]
                    if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                        $inner = trim(substr($value, 1, -1));
                        $value = array_filter(array_map('trim', explode(',', $inner)));
                        $value = array_values($value);
                    }

                    $result[$currentKey] = $value;
                    $currentKey = null;
                }
            } elseif (preg_match('/^([a-z_]+):$/', $line, $matches)) {
                // Start of array
                if ($currentKey && ! empty($currentArray)) {
                    $result[$currentKey] = $currentArray;
                }
                $currentKey = trim($matches[1]);
                $currentArray = [];
            }
        }

        // Save last array
        if ($currentKey && ! empty($currentArray)) {
            $result[$currentKey] = $currentArray;
        }

        return $result;
    }

    /**
     * Tokenize cooklang content into steps, sections, and notes.
     *
     * @return array<int, StepToken|SimpleToken>
     */
    private function tokenize(string $content): array
    {
        $this->ingredientsCache = [];
        $this->cookwareCache = [];

        $lines = explode("\n", $content);
        $tokens = [];
        $currentStep = '';
        $inComment = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Inside multi-line comment — skip until closing -]
            if ($inComment) {
                if (str_contains($trimmed, '-]')) {
                    $inComment = false;
                }

                continue;
            }

            // Blank line → flush current step
            if ($trimmed === '') {
                if ($currentStep !== '') {
                    $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
                    $currentStep = '';
                }

                continue;
            }

            // Section header: == Name == or = Name =
            if (preg_match('/^={1,2}\s+(.+?)\s*$/', $trimmed, $m)) {
                if ($currentStep !== '') {
                    $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
                    $currentStep = '';
                }
                $tokens = $this->appendToken($tokens, $this->simpleToken('section', $m[1]));

                continue;
            }

            // Unnamed section separator: ====
            if ($trimmed === '====') {
                if ($currentStep !== '') {
                    $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
                    $currentStep = '';
                }

                continue;
            }

            // Note block: > text
            if (preg_match('/^>\s*(.+)$/', $trimmed, $m)) {
                if ($currentStep !== '') {
                    $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
                    $currentStep = '';
                }
                $tokens = $this->appendToken($tokens, $this->simpleToken('note', $m[1]));

                continue;
            }

            // Cooklang comment block: [- ... -]
            if (str_starts_with($trimmed, '[-')) {
                if ($currentStep !== '') {
                    $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
                    $currentStep = '';
                }

                if (str_contains($trimmed, '-]')) {
                    // Single-line or opening+closing on same line: [- text -]
                    continue;
                }

                // Multi-line comment opening — skip until -]
                $inComment = true;

                continue;
            }

            // Collect step content with backslash line continuation
            $cleaned = $trimmed;
            if (str_ends_with($cleaned, '\\')) {
                $currentStep .= rtrim($cleaned, '\\').' ';
            } else {
                $currentStep .= $cleaned."\n";
            }
        }

        // Flush last step
        if ($currentStep !== '') {
            $tokens = $this->appendToken($tokens, $this->extractCooklangFeatures(trim($currentStep)));
        }

        return $tokens;
    }

    /**
     * @param  array<int, StepToken|SimpleToken>  $tokens
     * @param  StepToken|SimpleToken  $token
     * @return array<int, StepToken|SimpleToken>
     */
    private function appendToken(array $tokens, array $token): array
    {
        $tokens[] = $token;

        return $tokens;
    }

    /** @return SimpleToken */
    private function simpleToken(string $type, string $content): array
    {
        return [
            'type' => $type === 'section' ? 'section' : 'note',
            'content' => $content,
        ];
    }

    /**
     * Extract @ingredient, ~timer, #cookware from step content.
     *
     * Replaces cooklang syntax with display names in the step text
     * (e.g. "@flour{2%cup}" → "flour"), keeping the text readable.
     *
     * @return StepToken
     */
    private function extractCooklangFeatures(string $content): array
    {
        $ingredientRefs = [];
        $timerRefs = [];
        $cookwareRefs = [];

        foreach ($this->cooklangParser->parseSegments($content) as $segment) {
            if ($segment['type'] === 'ingredient') {
                $refId = md5('ing_'.$segment['content']);
                $ingredientRefs[] = $refId;
                $existing = $this->ingredientsCache[$refId] ?? null;
                $this->ingredientsCache[$refId] = [
                    'name' => $existing['name'] ?? $segment['content'],
                    'quantity' => $existing['quantity'] ?? $segment['quantity'],
                    'unit' => $existing['unit'] ?? $segment['unit'],
                    'preparation' => $existing['preparation'] ?? $segment['preparation'],
                ];
            } elseif ($segment['type'] === 'cookware') {
                $refId = md5('cw_'.$segment['content']);
                $cookwareRefs[] = $refId;
                $this->cookwareCache[$refId] = [
                    'name' => $segment['content'],
                    'quantity' => $segment['quantity'],
                    'unit' => $segment['unit'],
                ];
            } elseif ($segment['type'] === 'timer') {
                if ($segment['duration_raw'] === null) {
                    continue;
                }

                $timerRefs[] = [
                    'name' => $segment['content'] !== '' ? $segment['content'] : null,
                    'duration_raw' => str_replace('%', ' ', $segment['duration_raw']),
                ];
            }
        }

        return [
            'type' => 'step',
            'content' => trim($content),
            'ingredient_refs' => $ingredientRefs,
            'timer_refs' => $timerRefs,
            'cookware_refs' => $cookwareRefs,
        ];
    }
}
