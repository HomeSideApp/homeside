<?php

namespace App\Data\Households;

/**
 * Data for creating a new household.
 */
final readonly class CreateHouseholdData
{
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            color: $data['color'] ?? null,
        );
    }

    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $color = null,
    ) {}
}
