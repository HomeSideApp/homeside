<?php

namespace App\Data\Recipes;

final readonly class CreateRecipeData
{
    /**
     * @param  array<RecipeSectionData>  $sections
     * @param  array<RecipeIngredientData>  $ingredients
     * @param  array<RecipeStepData>  $steps
     * @param  array<RecipeCookwareData>  $cookware
     * @param  array<string>  $tags
     * @param  array<string>  $supplies
     * @param  array<string>|null  $suitableForDiet
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?int $servings,
        public ?string $yieldText,
        public ?int $prepTimeSeconds,
        public ?int $cookTimeSeconds,
        public ?int $totalTimeSeconds,
        public ?string $difficulty,
        public ?string $cuisine,
        public ?string $locale,
        public ?string $author,
        public ?string $sourceUrl,
        public ?string $sourceName,
        public ?string $sourceType,
        public ?string $coverImagePath,
        public ?string $cookingMethod,
        public ?string $recipeCategory,
        public ?string $collectionPath,
        public ?array $suitableForDiet,
        public array $tags,
        public array $sections,
        public array $ingredients,
        public array $steps,
        public array $cookware,
        public array $supplies,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sections = [];
        if (! empty($data['sections'])) {
            foreach ($data['sections'] as $s) {
                $sections[] = new RecipeSectionData(
                    id: $s['id'] ?? null,
                    client_id: $s['client_id'] ?? null,
                    name: $s['name'],
                    order: $s['order'] ?? 0,
                );
            }
        }

        $ingredients = [];
        if (! empty($data['ingredients'])) {
            foreach ($data['ingredients'] as $i) {
                $ingredients[] = new RecipeIngredientData(
                    id: $i['id'] ?? null,
                    client_id: $i['client_id'] ?? null,
                    name: $i['name'],
                    product_id: $i['product_id'] ?? null,
                    quantity: isset($i['quantity']) ? (float) $i['quantity'] : null,
                    quantity_text: $i['quantity_text'] ?? null,
                    unit: $i['unit'] ?? null,
                    preparation: $i['preparation'] ?? null,
                    notes: $i['notes'] ?? null,
                    optional: ! empty($i['optional']),
                    section_id: $i['section_id'] ?? null,
                    order: $i['order'] ?? 0,
                );
            }
        }

        $cookwareItems = [];
        if (! empty($data['cookware'])) {
            foreach ($data['cookware'] as $c) {
                $cookwareItems[] = new RecipeCookwareData(
                    id: $c['id'] ?? null,
                    client_id: $c['client_id'] ?? null,
                    name: $c['name'],
                    type: $c['type'] ?? 'tool',
                    quantity: isset($c['quantity']) ? (int) $c['quantity'] : null,
                    quantity_text: $c['quantity_text'] ?? null,
                    unit: $c['unit'] ?? null,
                    section_id: $c['section_id'] ?? null,
                    order: $c['order'] ?? 0,
                );
            }
        }

        $steps = [];
        if (! empty($data['steps'])) {
            foreach ($data['steps'] as $s) {
                $timers = [];
                if (! empty($s['timers'])) {
                    foreach ($s['timers'] as $t) {
                        $timers[] = new RecipeTimerData(
                            id: $t['id'] ?? null,
                            client_id: $t['client_id'] ?? null,
                            name: $t['name'] ?? null,
                            duration_seconds: $t['duration_seconds'] ?? 0,
                            order: $t['order'] ?? 0,
                        );
                    }
                }

                $steps[] = new RecipeStepData(
                    id: $s['id'] ?? null,
                    client_id: $s['client_id'] ?? null,
                    description: $s['description'],
                    image_path: $s['image_path'] ?? null,
                    section_id: $s['section_id'] ?? null,
                    ingredients: $s['ingredients'] ?? [],
                    cookware: $s['cookware'] ?? [],
                    timers: $timers,
                    order: $s['order'] ?? 0,
                    image_url: $s['image_url'] ?? null,
                    reference_recipe_ids: $s['reference_recipe_ids'] ?? [],
                );
            }
        }

        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            servings: isset($data['servings']) ? (int) $data['servings'] : null,
            yieldText: $data['yield_text'] ?? $data['yieldText'] ?? null,
            prepTimeSeconds: $data['prep_time_seconds'] ?? $data['prepTimeSeconds'] ?? null,
            cookTimeSeconds: $data['cook_time_seconds'] ?? $data['cookTimeSeconds'] ?? null,
            totalTimeSeconds: $data['total_time_seconds'] ?? $data['totalTimeSeconds'] ?? null,
            difficulty: $data['difficulty'] ?? null,
            cuisine: $data['cuisine'] ?? null,
            locale: $data['locale'] ?? null,
            author: $data['author'] ?? null,
            sourceUrl: $data['source_url'] ?? $data['sourceUrl'] ?? null,
            sourceName: $data['source_name'] ?? $data['sourceName'] ?? null,
            sourceType: $data['source_type'] ?? $data['sourceType'] ?? null,
            coverImagePath: $data['cover_image_path'] ?? $data['coverImagePath'] ?? null,
            cookingMethod: $data['cooking_method'] ?? $data['cookingMethod'] ?? null,
            recipeCategory: $data['recipe_category'] ?? $data['recipeCategory'] ?? null,
            collectionPath: $data['collection_path'] ?? $data['collectionPath'] ?? null,
            suitableForDiet: $data['suitable_for_diet'] ?? $data['suitableForDiet'] ?? null,
            tags: $data['tags'] ?? [],
            sections: $sections,
            ingredients: $ingredients,
            steps: $steps,
            cookware: $cookwareItems,
            supplies: $data['supplies'] ?? [],
            notes: $data['notes'] ?? null,
        );
    }
}
