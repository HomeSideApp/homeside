<?php

namespace App\Data\Recipes;

use App\Models\Recipe;

final readonly class RecipeData
{
    /**
     * @param  array<string>  $tags
     * @param  array<RecipeSectionData>  $sections
     * @param  array<RecipeIngredientData>  $ingredients
     * @param  array<RecipeStepData>  $steps
     * @param  array<RecipeCookwareData>  $cookware
     * @param  array<string>  $supplies
     * @param  array<string>|null  $suitableForDiet
     */
    public function __construct(
        public ?string $id,
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
        public ?string $coverImageUrl,
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
     * Crea una instancia de RecipeData a partir de un array plano (FormRequest).
     */
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
                );
            }
        }

        return new self(
            id: $data['id'] ?? null,
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
            coverImageUrl: $data['cover_image_path'] ?? $data['coverImageUrl'] ?? null,
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

    /**
     * Crea una instancia de RecipeData desde un modelo Recipe con relaciones cargadas.
     *
     * Requiere: sections, ingredients, steps.timers, steps.cookware,
     * steps.ingredients, cookware, tags.
     *
     * @param  Recipe  $recipe
     */
    public static function fromModel(object $recipe): self
    {
        $data = [
            'id' => (string) $recipe->id,
            'name' => $recipe->name,
            'description' => $recipe->description,
            'servings' => $recipe->servings,
            'yield_text' => $recipe->yield_text,
            'prep_time_seconds' => $recipe->prep_time_seconds,
            'cook_time_seconds' => $recipe->cook_time_seconds,
            'total_time_seconds' => $recipe->total_time_seconds,
            'difficulty' => $recipe->difficulty,
            'cuisine' => $recipe->cuisine,
            'locale' => $recipe->locale,
            'author' => $recipe->author,
            'source_url' => $recipe->source_url,
            'source_name' => $recipe->source_name,
            'source_type' => $recipe->source_type,
            'cover_image_path' => $recipe->cover_image_path,
            'cooking_method' => $recipe->cooking_method,
            'recipe_category' => $recipe->recipe_category,
            'suitable_for_diet' => $recipe->suitable_for_diet,
            'notes' => $recipe->notes,
            'tags' => $recipe->tags->pluck('name')->all(),
            'sections' => $recipe->sections->map(fn ($section): array => [
                'name' => $section->name,
                'order' => $section->order,
            ])->all(),
            'ingredients' => $recipe->ingredients->map(fn ($ingredient): array => [
                'name' => $ingredient->name,
                'product_id' => $ingredient->product_id !== null ? (string) $ingredient->product_id : null,
                'quantity' => $ingredient->quantity,
                'unit' => $ingredient->unit,
                'preparation' => $ingredient->preparation,
                'notes' => $ingredient->notes,
                'optional' => $ingredient->optional,
                'order' => $ingredient->order,
            ])->all(),
            'steps' => $recipe->steps->map(fn ($step): array => [
                'description' => $step->description,
                'image_path' => $step->image_path,
                'order' => $step->order,
                'ingredients' => $step->ingredients->pluck('id')->all(),
                'cookware' => $step->cookware->pluck('id')->all(),
                'timers' => $step->timers->map(fn ($timer): array => [
                    'name' => $timer->name,
                    'duration_seconds' => $timer->duration_seconds,
                    'order' => $timer->order,
                ])->all(),
            ])->all(),
            'cookware' => $recipe->cookware->map(fn ($cookware): array => [
                'name' => $cookware->name,
                'type' => $cookware->type,
                'quantity' => $cookware->quantity,
                'unit' => $cookware->unit,
                'order' => $cookware->order,
            ])->all(),
            'supplies' => [],
        ];

        return self::fromArray($data);
    }

    /**
     * Convierte el objeto a un array plano para serialización JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'servings' => $this->servings,
            'yield_text' => $this->yieldText,
            'prep_time_seconds' => $this->prepTimeSeconds,
            'cook_time_seconds' => $this->cookTimeSeconds,
            'total_time_seconds' => $this->totalTimeSeconds,
            'difficulty' => $this->difficulty,
            'cuisine' => $this->cuisine,
            'locale' => $this->locale,
            'author' => $this->author,
            'source_url' => $this->sourceUrl,
            'source_name' => $this->sourceName,
            'source_type' => $this->sourceType,
            'cover_image_url' => $this->coverImageUrl,
            'cooking_method' => $this->cookingMethod,
            'recipe_category' => $this->recipeCategory,
            'collection_path' => $this->collectionPath,
            'suitable_for_diet' => $this->suitableForDiet,
            'tags' => $this->tags,
            'sections' => array_map(
                fn (RecipeSectionData $s) => [
                    'id' => $s->id,
                    'client_id' => $s->client_id,
                    'name' => $s->name,
                    'order' => $s->order,
                ],
                $this->sections
            ),
            'ingredients' => array_map(
                fn (RecipeIngredientData $i) => [
                    'id' => $i->id,
                    'client_id' => $i->client_id,
                    'name' => $i->name,
                    'product_id' => $i->product_id,
                    'quantity' => $i->quantity,
                    'quantity_text' => $i->quantity_text,
                    'unit' => $i->unit,
                    'preparation' => $i->preparation,
                    'notes' => $i->notes,
                    'optional' => $i->optional,
                    'section_id' => $i->section_id,
                    'order' => $i->order,
                ],
                $this->ingredients
            ),
            'steps' => array_map(
                fn (RecipeStepData $s) => [
                    'id' => $s->id,
                    'client_id' => $s->client_id,
                    'description' => $s->description,
                    'image_path' => $s->image_path,
                    'image_url' => $s->image_url,
                    'section_id' => $s->section_id,
                    'ingredients' => $s->ingredients,
                    'cookware' => $s->cookware,
                    'timers' => array_map(
                        fn (RecipeTimerData $t) => [
                            'id' => $t->id,
                            'client_id' => $t->client_id,
                            'name' => $t->name,
                            'duration_seconds' => $t->duration_seconds,
                            'order' => $t->order,
                        ],
                        $s->timers
                    ),
                    'order' => $s->order,
                ],
                $this->steps
            ),
            'cookware' => array_map(
                fn (RecipeCookwareData $c) => [
                    'id' => $c->id,
                    'client_id' => $c->client_id,
                    'name' => $c->name,
                    'type' => $c->type,
                    'quantity' => $c->quantity,
                    'quantity_text' => $c->quantity_text,
                    'unit' => $c->unit,
                    'section_id' => $c->section_id,
                    'order' => $c->order,
                ],
                $this->cookware
            ),
            'supplies' => $this->supplies,
            'notes' => $this->notes,
        ];
    }
}
