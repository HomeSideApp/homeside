<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class CryptoPrice
 *
 * This class represents a spot price snapshot for a crypto asset in a quote currency. It extends
 * the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the price snapshot (UUID).
 * @property string $crypto_asset_id The id of the asset the price refers to.
 * @property string $quote_currency The ISO currency code used as quote.
 * @property string $price The price expressed as a decimal string.
 * @property Carbon $fetched_at The timestamp when the price was fetched from the provider.
 * @property Carbon|null $created_at The timestamp when the snapshot was stored.
 * @property Carbon|null $updated_at The timestamp when the snapshot was last updated.
 *
 * Relationships:
 * @property CryptoAsset $asset The asset the price refers to.
 *
 * @mixin Model
 */
class CryptoPrice extends Model
{
    use HasUuids;

    protected $fillable = [
        'crypto_asset_id', 'quote_currency', 'price', 'fetched_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * The asset the price refers to.
     *
     * @return BelongsTo<CryptoAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id');
    }

    /**
     * Scope the query to the most recent snapshot per asset for a quote currency.
     *
     * The latest snapshot is resolved with a correlated subquery so the valuation reads a single
     * cached row per asset instead of aggregating the full history.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $quoteCurrency  The quote currency to resolve.
     * @return Builder<self> The query scoped to the latest snapshots.
     */
    public function scopeLatestPerAsset(Builder $query, string $quoteCurrency): Builder
    {
        return $query
            ->where('quote_currency', $quoteCurrency)
            ->whereRaw(
                'crypto_prices.fetched_at = (
                    SELECT MAX(inner_prices.fetched_at)
                    FROM crypto_prices AS inner_prices
                    WHERE inner_prices.crypto_asset_id = crypto_prices.crypto_asset_id
                      AND inner_prices.quote_currency = crypto_prices.quote_currency
                )',
            );
    }
}
