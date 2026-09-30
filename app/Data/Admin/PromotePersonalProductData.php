<?php

namespace App\Data\Admin;

/**
 * Data for promoting a personal product to a global catalog product.
 */
final readonly class PromotePersonalProductData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            product_id: $data['product_id'],
            category_id: $data['category_id'] ?? null,
        );
    }

    public function __construct(
        public string $product_id,
        public ?string $category_id,
    ) {}
}
