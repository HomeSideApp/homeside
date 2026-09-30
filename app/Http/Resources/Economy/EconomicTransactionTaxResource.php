<?php

namespace App\Http\Resources\Economy;

use App\Http\Resources\Economy\Concerns\FormatsMinorAmounts;
use App\Models\EconomicTransactionTax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicTransactionTax */
final class EconomicTransactionTaxResource extends JsonResource
{
    use FormatsMinorAmounts;

    /**
     * Transform a tax breakdown entry into its public representation.
     *
     * `tax_amount` is the field the web detail table renders, while `amount_minor` keeps the
     * integer value used by the mobile contract.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized tax entry.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => $this->rate / 100,
            'taxable_base' => $this->formatMinor($this->taxable_base_minor),
            'tax_amount' => $this->formatMinor($this->tax_amount_minor),
            'amount' => $this->formatMinor($this->tax_amount_minor),
            'taxable_base_minor' => $this->taxable_base_minor,
            'amount_minor' => $this->tax_amount_minor,
        ];
    }
}
