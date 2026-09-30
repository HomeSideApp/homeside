<?php

namespace App\Data\Contacts;

final readonly class ContactProviderCapabilitiesData
{
    public function __construct(
        public bool $read,
        public bool $write,
        public bool $incrementalSync,
        public bool $collections,
        public bool $groups,
        public bool $photos,
        public bool $organizations,
    ) {}
}
