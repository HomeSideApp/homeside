<?php

namespace App\Data\Contacts;

final readonly class ExternalCollectionData
{
    public function __construct(public string $remoteId, public string $name, public string $href, public bool $readOnly = true) {}
}
