<?php

namespace App\Data\Recipes;

/**
 * Data for a single recipe ingredient, optionally linked to a product.
 */
final readonly class RecipeIngredientData
{
    public function __construct(
        public ?string $id,
        public ?string $client_id,
        public string $name,
        public ?string $product_id,
        public ?float $quantity,
        public ?string $quantity_text,
        public ?string $unit,
        public ?string $preparation,
        public ?string $notes,
        public bool $optional,
        public ?string $section_id,
        public int $order,
    ) {}
}
