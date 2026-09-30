<?php

namespace App\Data\ShoppingLists;

/**
 * Data for creating a new shopping list.
 */
final readonly class CreateShoppingListData
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
