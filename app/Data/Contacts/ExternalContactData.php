<?php

namespace App\Data\Contacts;

final readonly class ExternalContactData
{
    /**
     * @param  list<ContactValueData>  $emails
     * @param  list<ContactValueData>  $phones
     * @param  list<ContactValueData>  $addresses
     * @param  list<ContactValueData>  $urls
     * @param  list<string>  $categories
     */
    public function __construct(
        public string $remoteId,
        public string $formattedName,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $organization = null,
        public ?string $jobTitle = null,
        public ?string $birthday = null,
        public ?string $notes = null,
        public array $emails = [],
        public array $phones = [],
        public array $addresses = [],
        public array $urls = [],
        public ?string $photoBytes = null,
        public ?string $etag = null,
        public ?string $href = null,
        public ?string $uid = null,
        public ?string $additionalName = null,
        public ?string $nickname = null,
        /** @var list<array{kind: string, label: ?string, value: string, value_type: string}> */
        public array $dates = [],
        /** @var list<array{type: string, name: ?string, external_value: ?string}> */
        public array $relations = [],
        public ?string $photoUri = null,
        public array $categories = [],
    ) {}
}
