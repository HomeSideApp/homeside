<?php

namespace App\Data\ShoppingLists;

/**
 * Data for updating an existing shopping list.
 */
final readonly class UpdateShoppingListData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
        );
    }

    public function __construct(
        public string $name,
    ) {}
}
