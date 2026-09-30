<?php

namespace App\Data\Contacts;

final readonly class ContactSyncCursorData
{
    /** @param array<string, mixed> $state */
    public function __construct(public ?string $value, public ?string $type, public array $state = []) {}
}
