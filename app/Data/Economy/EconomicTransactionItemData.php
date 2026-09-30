<?php

namespace App\Data\Economy;

/**
 * Data for a single line item of an economic transaction.
 */
final readonly class EconomicTransactionItemData
{
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            quantity: (int) ($data['quantity'] ?? 1),
            unit_amount_minor: isset($data['unit_amount_minor'])
                ? (int) $data['unit_amount_minor']
                : (int) round($data['unit_amount'] * 100),
            subtotal_minor: isset($data['subtotal_minor'])
                ? (int) $data['subtotal_minor']
                : (int) round($data['subtotal'] * 100),
            tax_amount_minor: isset($data['tax_amount_minor'])
                ? (int) $data['tax_amount_minor']
                : (isset($data['tax_amount']) ? (int) round($data['tax_amount'] * 100) : null),
            total_minor: isset($data['total_minor'])
                ? (int) $data['total_minor']
                : (int) round($data['total'] * 100),
        );
    }

    public function __construct(
        public string $name,
        public int $quantity,
        public int $unit_amount_minor,
        public int $subtotal_minor,
        public ?int $tax_amount_minor,
        public int $total_minor,
    ) {}
}
