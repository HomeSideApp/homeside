<?php

namespace App\Models;

use Database\Factories\CryptoAssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class CryptoAsset
 *
 * This class represents a supported crypto asset (Bitcoin, Ethereum, ...) together with the
 * decimal precision of its smallest unit. It extends the Eloquent Model class and uses the
 * HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the asset (UUID).
 * @property string $symbol The ticker symbol of the asset.
 * @property string $name The display name of the asset.
 * @property string $coingecko_id The provider identifier used to fetch its market price.
 * @property int $decimal_places The number of minor units per asset unit.
 * @property string|null $icon The icon of the asset, if any.
 * @property bool $is_active Whether the asset can be selected for new accounts.
 * @property Carbon|null $created_at The timestamp when the asset was created.
 * @property Carbon|null $updated_at The timestamp when the asset was last updated.
 *
 * Relationships:
 * @property Collection<int, CryptoPrice> $prices The stored market price snapshots.
 * @property Collection<int, EconomicAccount> $accounts The accounts holding this asset.
 *
 * @mixin Model
 */
class CryptoAsset extends Model
{
    /** @use HasFactory<CryptoAssetFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'symbol', 'name', 'coingecko_id', 'decimal_places', 'icon', 'is_active',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The stored market price snapshots.
     *
     * @return HasMany<CryptoPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(CryptoPrice::class, 'crypto_asset_id');
    }

    /**
     * The accounts holding this asset.
     *
     * @return HasMany<EconomicAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(EconomicAccount::class, 'crypto_asset_id');
    }

    /**
     * Scope the query to the active assets.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @return Builder<self> The query scoped to active assets.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
