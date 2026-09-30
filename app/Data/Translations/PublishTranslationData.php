<?php

namespace App\Data\Translations;

/**
 * Data for publishing or unpublishing the translation of an entity for a locale.
 */
final readonly class PublishTranslationData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            translatableType: (string) $data['translatable_type'],
            translatableId: (string) $data['translatable_id'],
            locale: (string) $data['locale'],
            publish: (bool) $data['publish'],
        );
    }

    public function __construct(
        public string $translatableType,
        public string $translatableId,
        public string $locale,
        public bool $publish,
    ) {}
}
