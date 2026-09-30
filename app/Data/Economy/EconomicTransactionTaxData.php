<?php

namespace App\Data\Economy;

/**
 * Data for a tax breakdown line of an economic transaction.
 */
final readonly class EconomicTransactionTaxData
{
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            rate: (int) ($data['rate'] * 100), // 21 → 2100
            taxable_base_minor: isset($data['taxable_base_minor'])
                ? (int) $data['taxable_base_minor']
                : (int) round($data['taxable_base'] * 100),
            tax_amount_minor: isset($data['amount_minor'])
                ? (int) $data['amount_minor']
                : (int) round($data['amount'] * 100),
        );
    }

    public function __construct(
        public string $name,
        public int $rate,
        public int $taxable_base_minor,
        public int $tax_amount_minor,
    ) {}
}
