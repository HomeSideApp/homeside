<?php

namespace App\Http\Resources\Economy;

use App\Http\Resources\Economy\Concerns\FormatsMinorAmounts;
use App\Models\EconomicTransactionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicTransactionItem */
final class EconomicTransactionItemResource extends JsonResource
{
    use FormatsMinorAmounts;

    /**
     * Transform a transaction line item into its public representation.
     *
     * Both the minor units and their decimal formatted counterparts are exposed: the mobile
     * contract consumes the integers while the web pages render the strings.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized line item.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_amount' => $this->formatMinor($this->unit_amount_minor),
            'subtotal' => $this->formatMinor($this->subtotal_minor),
            'tax_amount' => $this->formatMinor($this->tax_amount_minor),
            'total' => $this->formatMinor($this->total_minor),
            'unit_amount_minor' => $this->unit_amount_minor,
            'subtotal_minor' => $this->subtotal_minor,
            'tax_amount_minor' => $this->tax_amount_minor,
            'total_minor' => $this->total_minor,
            'position' => $this->position,
        ];
    }
}
