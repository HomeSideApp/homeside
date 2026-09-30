<?php

namespace App\Data\Recipes;

/**
 * Data for forking a recipe into the user's collection.
 */
final readonly class ForkRecipeData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
