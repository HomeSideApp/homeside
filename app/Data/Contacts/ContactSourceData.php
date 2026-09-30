<?php

namespace App\Data\Contacts;

final readonly class ContactSourceData
{
    /** @param list<string> $selectedCollections */
    public function __construct(
        public ?string $householdId,
        public string $name,
        public string $serverUrl,
        public ?string $username,
        public ?string $appPassword,
        public bool $enabled,
        public bool $syncEnabled,
        public array $selectedCollections,
        public ?string $favoriteLabel = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?string $householdId = null): self
    {
        return new self(
            $data['household_id'] ?? $householdId,
            $data['name'],
            $data['server_url'],
            $data['username'] ?? null,
            $data['app_password'] ?? null,
            $data['enabled'] ?? true,
            $data['sync_enabled'] ?? true,
            $data['selected_collections'] ?? [],
            isset($data['favorite_label']) ? trim($data['favorite_label']) : null,
        );
    }
}
