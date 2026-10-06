<?php

namespace App\Enums;

enum HouseholdAccessLevel: string
{
    case None = 'none';
    case Read = 'read';
    case Write = 'write';

    public function allows(bool $write = false): bool
    {
        return $write ? $this === self::Write : $this !== self::None;
    }
}
