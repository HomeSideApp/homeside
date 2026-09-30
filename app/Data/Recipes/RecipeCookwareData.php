<?php

namespace App\Data\Recipes;

/**
 * Data for a piece of cookware used in a recipe.
 */
final readonly class RecipeCookwareData
{
    public function __construct(
        public ?string $id,
        public ?string $client_id,
        public ?string $name,
        public string $type,
        public ?int $quantity,
        public ?string $quantity_text,
        public ?string $unit,
        public ?string $section_id,
        public int $order,
    ) {}
}
