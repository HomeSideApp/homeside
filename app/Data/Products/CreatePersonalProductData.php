<?php

namespace App\Data\Products;

/**
 * Data for creating a personal product from a recipe ingredient.
 */
final readonly class CreatePersonalProductData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            category_id: $data['category_id'] ?? null,
            icon: $data['icon'] ?? null,
        );
    }

    public function __construct(
        public string $name,
        public ?string $category_id,
        public ?string $icon,
    ) {}
}
