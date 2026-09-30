<?php

namespace App\Data\Translations;

/**
 * Data for generating translations with AI for a translatable type and locale.
 * Consumed by the upcoming GenerateTranslationsWithAi action (Phase 5).
 */
final readonly class GenerateTranslationsData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            translatableType: (string) $data['translatable_type'],
            locale: (string) $data['locale'],
            ids: isset($data['ids']) && is_array($data['ids'])
                ? array_values(array_map(static fn (mixed $id): string => (string) $id, $data['ids']))
                : null,
        );
    }

    /**
     * @param  list<string>|null  $ids  Optional entity ids to limit the generation; null means all entities of the type.
     */
    public function __construct(
        public string $translatableType,
        public string $locale,
        public ?array $ids = null,
    ) {}
}
