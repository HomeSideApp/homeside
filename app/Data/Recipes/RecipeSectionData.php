<?php

namespace App\Data\Recipes;

/**
 * Data for a named section within a recipe.
 */
final readonly class RecipeSectionData
{
    public function __construct(
        public ?string $id,
        public ?string $client_id,
        public string $name,
        public int $order,
    ) {}
}
