<?php

namespace App\Data\Translations;

use App\Enums\TranslationFieldStatus;

/**
 * Data for updating an existing translation.
 */
final readonly class UpdateTranslationData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            value: (string) $data['value'],
            status: isset($data['status'])
                ? TranslationFieldStatus::from((string) $data['status'])
                : null,
        );
    }

    public function __construct(
        public string $value,
        public ?TranslationFieldStatus $status = null,
    ) {}
}
