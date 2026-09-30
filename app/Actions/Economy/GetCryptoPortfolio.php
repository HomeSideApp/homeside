<?php

namespace App\Actions\Economy;

use App\Models\CryptoAsset;
use App\Models\CryptoPrice;
use App\Models\EconomicAccount;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Values the crypto holdings of a user using the latest stored price snapshots.
 */
final class GetCryptoPortfolio
{
    /**
     * Build the crypto portfolio valuation for a user.
     *
     * The method reads only stored snapshots, so a provider outage never breaks the request: assets
     * without a known price are reported with a null value instead of a misleading zero.
     *
     * @param  User  $user  The user whose crypto holdings are valued.
     * @return array{base_currency: string, total_value_minor: int, updated_at: string|null, has_prices: bool, slices: array<int, array<string, mixed>>} The portfolio summary and its per-asset breakdown.
     */
    public function execute(User $user): array
    {
        $baseCurrency = (string) config('services.coingecko.quote_currency', 'EUR');

        $accounts = EconomicAccount::query()
            ->ownedBy($user->id)
            ->active()
            ->whereNotNull('crypto_asset_id')
            ->with('cryptoAsset')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'base_currency' => $baseCurrency,
                'total_value_minor' => 0,
                'updated_at' => null,
                'has_prices' => false,
                'slices' => [],
            ];
        }

        $balances = (new GetAccountBalances)->execute($user, $accounts);
        $byAsset = $this->groupBalancesByAsset($accounts, $balances);

        $prices = CryptoPrice::query()
            ->latestPerAsset($baseCurrency)
            ->whereIn('crypto_asset_id', $accounts->pluck('crypto_asset_id')->unique())
            ->get()
            ->keyBy('crypto_asset_id');

        $slices = [];
        $totalMinor = 0;
        $latestFetchedAt = null;

        foreach ($byAsset as $assetId => $group) {
            $asset = $group['asset'];
            $price = $prices->get($assetId);
            $priceValue = $price !== null ? (float) $price->price : null;
            $quantityMinor = $group['quantity_minor'];

            $valueMinor = $priceValue === null
                ? null
                : $this->toMinorUnits(
                    ($quantityMinor / (10 ** $asset->decimal_places)) * $priceValue,
                    2,
                );

            if ($valueMinor !== null) {
                $totalMinor += $valueMinor;
            }

            if ($price !== null && ($latestFetchedAt === null || $price->fetched_at->greaterThan($latestFetchedAt))) {
                $latestFetchedAt = $price->fetched_at;
            }

            $slices[] = [
                'asset_id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'decimal_places' => $asset->decimal_places,
                'quantity_minor' => $quantityMinor,
                'price' => $priceValue,
                'value_minor' => $valueMinor,
                'accounts_count' => $group['accounts_count'],
            ];
        }

        usort($slices, fn (array $left, array $right): int => ($right['value_minor'] ?? 0) <=> ($left['value_minor'] ?? 0));

        return [
            'base_currency' => $baseCurrency,
            'total_value_minor' => $totalMinor,
            'updated_at' => $latestFetchedAt instanceof Carbon ? $latestFetchedAt->toISOString() : null,
            'has_prices' => $latestFetchedAt !== null,
            'slices' => $slices,
        ];
    }

    /**
     * Group account balances by crypto asset.
     *
     * @param  Collection<int, EconomicAccount>  $accounts  The crypto accounts owned by the user.
     * @param  array<string, int>  $balances  The resolved balances keyed by account identifier.
     * @return array<string, array{asset: CryptoAsset, quantity_minor: int, accounts_count: int}> The grouped quantities keyed by asset identifier.
     */
    private function groupBalancesByAsset(Collection $accounts, array $balances): array
    {
        $grouped = [];

        foreach ($accounts as $account) {
            $asset = $account->cryptoAsset;

            if ($asset === null) {
                continue;
            }

            $grouped[$asset->id] ??= [
                'asset' => $asset,
                'quantity_minor' => 0,
                'accounts_count' => 0,
            ];

            $grouped[$asset->id]['quantity_minor'] += $balances[$account->id] ?? 0;
            $grouped[$asset->id]['accounts_count']++;
        }

        return $grouped;
    }

    /**
     * Convert a decimal amount into minor units with the given precision.
     *
     * @param  float  $amount  The decimal amount to convert.
     * @param  int  $decimalPlaces  The number of minor units per currency unit.
     * @return int The amount expressed in minor units.
     */
    private function toMinorUnits(float $amount, int $decimalPlaces): int
    {
        return (int) round($amount * (10 ** $decimalPlaces));
    }
}
