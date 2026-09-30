<?php

declare(strict_types=1);

namespace App\Ai\Execution;

/**
 * DTO for the structured recipe generation result.
 *
 * Maps exactly the JSON schema produced by the agent via structured output.
 * The SDK handles the serialization/deserialization.
 */
final readonly class RecipeGenerationResultData
{
    /**
     * @param  string  $name  The recipe name
     * @param  string|null  $description  Short description
     * @param  int|null  $servings  Number of servings
     * @param  string|null  $yieldText  Textual yield
     * @param  int|null  $prepTimeSeconds  Preparation time in seconds
     * @param  int|null  $cookTimeSeconds  Cooking time in seconds
     * @param  int|null  $totalTimeSeconds  Total time in seconds
     * @param  string|null  $difficulty  Difficulty level
     * @param  string|null  $cuisine  Cuisine type
     * @param  string|null  $cookingMethod  Cooking method
     * @param  string|null  $recipeCategory  Category
     * @param  string|null  $author  Author
     * @param  string|null  $notes  Additional notes
     * @param  list<string>  $tags  Tags
     * @param  list<array{name: string, ingredients: list<array<string, mixed>>, steps: list<array<string, mixed>>}>  $sections  Sections
     * @param  list<array{name: string, quantity: float|null, unit: string|null, notes: string|null, product_id: string|null}>  $ingredients  Ingredients
     * @param  list<array{step_number: int, description: string, duration_seconds: int|null}>  $steps  Steps
     * @param  list<array{name: string}>  $cookware  Cookware
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?int $servings = null,
        public ?string $yieldText = null,
        public ?int $prepTimeSeconds = null,
        public ?int $cookTimeSeconds = null,
        public ?int $totalTimeSeconds = null,
        public ?string $difficulty = null,
        public ?string $cuisine = null,
        public ?string $cookingMethod = null,
        public ?string $recipeCategory = null,
        public ?string $author = null,
        public ?string $notes = null,
        /** @var list<string> */
        public array $tags = [],
        /** @var list<array{name: string, ingredients: list<array<string, mixed>>, steps: list<array<string, mixed>>}> */
        public array $sections = [],
        /** @var list<array{name: string, quantity: float|null, unit: string|null, notes: string|null, product_id: string|null}> */
        public array $ingredients = [],
        /** @var list<array{step_number: int, description: string, duration_seconds: int|null}> */
        public array $steps = [],
        /** @var list<array{name: string}> */
        public array $cookware = [],
    ) {}

    /**
     * Convert to an array for the frontend (legacy-compatible format).
     *
     * @return array{
     *     name: string, description: ?string, servings: ?int, yield_text: ?string,
     *     prep_time_seconds: ?int, cook_time_seconds: ?int, total_time_seconds: ?int,
     *     difficulty: ?string, cuisine: ?string, cooking_method: ?string,
     *     recipe_category: ?string, author: ?string, notes: ?string, tags: list<string>,
     *     sections: list<array{name: string, ingredients: list<array<string, mixed>>, steps: list<array<string, mixed>>}>,
     *     ingredients: list<array{name: string, quantity: float|null, unit: string|null, notes: string|null, product_id: string|null}>,
     *     steps: list<array{step_number: int, description: string, duration_seconds: int|null}>,
     *     cookware: list<array{name: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'servings' => $this->servings,
            'yield_text' => $this->yieldText,
            'prep_time_seconds' => $this->prepTimeSeconds,
            'cook_time_seconds' => $this->cookTimeSeconds,
            'total_time_seconds' => $this->totalTimeSeconds,
            'difficulty' => $this->difficulty,
            'cuisine' => $this->cuisine,
            'cooking_method' => $this->cookingMethod,
            'recipe_category' => $this->recipeCategory,
            'author' => $this->author,
            'notes' => $this->notes,
            'tags' => $this->tags,
            'sections' => $this->sections,
            'ingredients' => $this->ingredients,
            'steps' => $this->steps,
            'cookware' => $this->cookware,
        ];
    }
}
