<?php

namespace App\Enums;

/**
 * Origin of a household tag.
 */
enum HouseholdTagType: string
{
    case Predefined = 'predefined';
    case Custom = 'custom';

    /**
     * Get the human-readable label for the tag type.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Predefined => 'Predefined',
            self::Custom => 'Custom',
        };
    }
}
