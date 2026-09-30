<?php

namespace App\Data\Economy;

final readonly class CreateEconomicImportData
{
    /**
     * @param  array<string, bool>  $requested_sections
     */
    public function __construct(
        public string $document_id,
        public array $requested_sections,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            document_id: (string) $data['document_id'],
            requested_sections: $data['sections'] ?? [
                'place' => true,
                'date' => true,
                'items' => true,
                'taxes' => true,
            ],
        );
    }
}
