<?php

namespace App\Enums;

/**
 * Who or what created a registration block.
 *
 * This is separate from the audit `channel`/`origin` fields: `source` answers
 * "was this a human decision or an automated one?", which matters when a
 * later operator reviews whether a block should be released.
 */
enum RegistrationBlockSource: string
{
    case Admin = 'admin';
    case System = 'system';

    /**
     * Get the human-readable label for the source.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::System => 'System',
        };
    }
}
