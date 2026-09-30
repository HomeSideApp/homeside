<?php

namespace App\Data\Economy;

/**
 * Data for a tax breakdown extracted from a receipt.
 */
final readonly class ReceiptTaxAnalysisData
{
    public function __construct(
        public string $name,
        public int $rate,
        public int $taxable_base_minor,
        public int $tax_amount_minor,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            rate: (int) round((float) $data['rate'] * 100),
            taxable_base_minor: (int) round((float) $data['taxable_base'] * 100),
            tax_amount_minor: (int) round((float) $data['amount'] * 100),
        );
    }
}
