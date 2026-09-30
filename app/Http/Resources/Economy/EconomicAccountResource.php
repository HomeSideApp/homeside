<?php

namespace App\Http\Resources\Economy;

use App\Models\EconomicAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EconomicAccount */
final class EconomicAccountResource extends JsonResource
{
    /**
     * Transform an economic account into its public API representation.
     *
     * The `balance_minor` value is passed through the `$additional` array by the caller, which
     * resolves all balances in a single aggregated query.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized account, including its payment method when loaded.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency' => $this->currency,
            'decimal_places' => $this->decimal_places,
            'crypto_asset_id' => $this->crypto_asset_id,
            'crypto_asset' => $this->whenLoaded(
                'cryptoAsset',
                fn (): ?array => $this->cryptoAsset === null ? null : [
                    'id' => $this->cryptoAsset->id,
                    'symbol' => $this->cryptoAsset->symbol,
                    'name' => $this->cryptoAsset->name,
                    'decimal_places' => $this->cryptoAsset->decimal_places,
                ],
            ),
            'initial_balance_minor' => $this->initial_balance_minor,
            'initial_balance_at' => $this->initial_balance_at?->toDateString(),
            'balance_minor' => $this->additional['balance_minor'] ?? null,
            'icon' => $this->icon,
            'color' => $this->color,
            'last_four_digits' => $this->last_four_digits,
            'include_in_totals' => $this->include_in_totals,
            'archived_at' => $this->archived_at?->toISOString(),
            'is_archived' => $this->isArchived(),
            'payment_methods' => $this->whenLoaded(
                'paymentMethods',
                fn (): array => PaymentMethodResource::collection($this->paymentMethods)->resolve($request),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
