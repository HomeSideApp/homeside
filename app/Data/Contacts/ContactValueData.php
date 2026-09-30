<?php

namespace App\Data\Contacts;

final readonly class ContactValueData
{
    public function __construct(public string $value, public ?string $type, public bool $preferred) {}

    /** @param array{value: string, type?: string|null, preferred?: bool} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['value'], $data['type'] ?? null, $data['preferred'] ?? false);
    }
}
