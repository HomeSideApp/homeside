<?php

namespace App\Data\Economy;

/**
 * Data for a single item extracted from a receipt.
 */
final readonly class ReceiptItemAnalysisData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unit_amount_minor,
        public int $total_minor,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            quantity: (int) ($data['quantity'] ?? 1),
            unit_amount_minor: (int) round((float) $data['unit_amount'] * 100),
            total_minor: (int) round((float) $data['total'] * 100),
        );
    }
}
