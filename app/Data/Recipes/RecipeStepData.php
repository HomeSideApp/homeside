<?php

namespace App\Data\Recipes;

final readonly class RecipeStepData
{
    /**
     * @param  array<string>  $ingredients  Array de client_ids
     * @param  array<RecipeCookwareData>  $cookware
     * @param  array<RecipeTimerData>  $timers
     * @param  array<int, string>  $reference_recipe_ids  Recetas enlazadas por orden de aparición en la descripción
     */
    public function __construct(
        public ?string $id,
        public ?string $client_id,
        public string $description,
        public ?string $image_path,
        public ?string $section_id,
        public array $ingredients,
        public array $cookware,
        public array $timers,
        public int $order,
        public ?string $image_url = null,
        public array $reference_recipe_ids = [],
    ) {}
}
