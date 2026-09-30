<?php

namespace App\Data\Economy;

use Illuminate\Support\Carbon;

final readonly class ReceiptAnalysisResultData
{
    /**
     * @param  ReceiptItemAnalysisData[]  $items
     * @param  ReceiptTaxAnalysisData[]  $taxes
     */
    public function __construct(
        public ?string $title,
        public ?int $amount_minor,
        public string $currency,
        public ?string $place,
        public ?Carbon $occurred_at,
        public array $items,
        public array $taxes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            amount_minor: isset($data['amount']) ? (int) round((float) $data['amount'] * 100) : null,
            currency: $data['currency'] ?? 'EUR',
            place: $data['place'] ?? null,
            occurred_at: isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
            items: array_map(
                fn (array $item) => ReceiptItemAnalysisData::fromArray($item),
                $data['items'] ?? []
            ),
            taxes: array_map(
                fn (array $tax) => ReceiptTaxAnalysisData::fromArray($tax),
                $data['taxes'] ?? []
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'amount' => $this->amount_minor !== null ? number_format($this->amount_minor / 100, 2, '.', '') : null,
            'currency' => $this->currency,
            'place' => $this->place,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'items' => array_map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_amount' => number_format($item->unit_amount_minor / 100, 2, '.', ''),
                'total' => number_format($item->total_minor / 100, 2, '.', ''),
            ], $this->items),
            'taxes' => array_map(fn ($tax) => [
                'name' => $tax->name,
                'rate' => number_format($tax->rate / 100, 2, '.', ''),
                'taxable_base' => number_format($tax->taxable_base_minor / 100, 2, '.', ''),
                'amount' => number_format($tax->tax_amount_minor / 100, 2, '.', ''),
            ], $this->taxes),
        ];
    }
}
