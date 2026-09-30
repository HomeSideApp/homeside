<?php

namespace App\Data\Translations;

use App\Enums\TranslationFieldStatus;

/**
 * Data for creating a new translation.
 */
final readonly class CreateTranslationData
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
            field: (string) $data['field'],
            value: (string) $data['value'],
            status: isset($data['status'])
                ? TranslationFieldStatus::from((string) $data['status'])
                : TranslationFieldStatus::Pending,
        );
    }

    public function __construct(
        public string $translatableType,
        public string $translatableId,
        public string $locale,
        public string $field,
        public string $value,
        public TranslationFieldStatus $status = TranslationFieldStatus::Pending,
    ) {}

    /**
     * Return a copy of the data with the locale normalised to its canonical
     * BCP 47 form.
     */
    public function withLocale(string $locale): self
    {
        return new self(
            translatableType: $this->translatableType,
            translatableId: $this->translatableId,
            locale: $locale,
            field: $this->field,
            value: $this->value,
            status: $this->status,
        );
    }
}
