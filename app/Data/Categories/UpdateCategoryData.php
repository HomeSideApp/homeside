<?php

namespace App\Data\Categories;

/**
 * Data for updating an existing category.
 */
final readonly class UpdateCategoryData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            color: (string) $data['color'],
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function __construct(
        public string $name,
        public string $color,
        public ?bool $isActive = null,
    ) {}
}
