<?php

namespace App\Enums;

/**
 * Status of an entity translation for a specific locale.
 */
enum TranslationEntityStatus: string
{
    /** The entity does not have a complete translation for this locale. Not shown to users. */
    case Incomplete = 'incomplete';

    /** All translatable fields have a translation for this locale. Not shown to users until published. */
    case Complete = 'complete';

    /** The entity translation is published for this locale. The only status shown to users. */
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Incomplete => __('app.translation_entity_statuses.incomplete'),
            self::Complete => __('app.translation_entity_statuses.complete'),
            self::Published => __('app.translation_entity_statuses.published'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
