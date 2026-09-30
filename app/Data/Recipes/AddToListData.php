<?php

namespace App\Data\Recipes;

/**
 * Data for adding a recipe to a shopping list, holding the
 * number of servings to scale the ingredient quantities to.
 */
final readonly class AddToListData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            target_servings: (int) ($data['target_servings'] ?? 1),
        );
    }

    public function __construct(
        public int $target_servings,
    ) {}
}
