<?php

namespace App\Data\Contacts;

final readonly class ContactData
{
    /**
     * @param  list<ContactValueData>  $emails
     * @param  list<ContactValueData>  $phones
     * @param  list<ContactValueData>  $addresses
     * @param  list<ContactValueData>  $urls
     * @param  list<array{kind: string, label: ?string, value: string}>  $dates
     * @param  list<array{type: string, related_contact_id: ?string, name: ?string}>  $relations
     */
    public function __construct(
        public ?string $householdId,
        public string $type,
        public string $displayName,
        public ?string $givenName,
        public ?string $familyName,
        public ?string $additionalName,
        public ?string $nickname,
        public ?string $organization,
        public ?string $jobTitle,
        public ?string $birthday,
        public ?string $notes,
        public array $emails,
        public array $phones,
        public array $addresses,
        public array $urls,
        public array $dates,
        public array $relations,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?string $householdId = null): self
    {
        return new self(
            $data['household_id'] ?? $householdId,
            $data['type'],
            $data['display_name'],
            $data['given_name'] ?? null,
            $data['family_name'] ?? null,
            $data['additional_name'] ?? null,
            $data['nickname'] ?? null,
            $data['organization'] ?? null,
            $data['job_title'] ?? null,
            $data['birthday'] ?? null,
            $data['notes'] ?? null,
            array_map(ContactValueData::fromArray(...), $data['emails'] ?? []),
            array_map(ContactValueData::fromArray(...), $data['phones'] ?? []),
            array_map(ContactValueData::fromArray(...), $data['addresses'] ?? []),
            array_map(ContactValueData::fromArray(...), $data['urls'] ?? []),
            array_map(static fn (array $date): array => [
                'kind' => $date['kind'],
                'label' => $date['label'] ?? null,
                'value' => $date['value'],
            ], $data['dates'] ?? []),
            array_map(static fn (array $relation): array => [
                'type' => $relation['type'],
                'related_contact_id' => $relation['related_contact_id'] ?? null,
                'name' => $relation['name'] ?? null,
            ], $data['relations'] ?? []),
        );
    }
}
