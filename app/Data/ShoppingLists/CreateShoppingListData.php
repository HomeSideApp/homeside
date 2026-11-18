<?php

namespace App\Data\ShoppingLists;

/**
 * Data for creating a new shopping list.
 */
final readonly class CreateShoppingListData
{
    /**
     * @param  array<string, mixed>  $data
     * @param  string|null  $id  The client-supplied UUIDv7 identity, or null when the server assigns one.
     */
    public static function fromArray(array $data, ?string $id = null): self
    {
        return new self(
            name: (string) $data['name'],
            id: $id,
        );
    }

    /**
     * @param  string  $name  The user-facing list name.
     * @param  string|null  $id  The client-supplied UUIDv7 identity, or null when the server assigns one.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $name,
        public ?string $id = null,
    ) {}
}
