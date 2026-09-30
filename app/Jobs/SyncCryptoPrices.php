<?php

namespace App\Jobs;

use App\Contracts\CryptoPriceFetcher;
use App\Models\CryptoAsset;
use App\Models\CryptoPrice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronises the crypto price snapshots used to value crypto accounts.
 */
final class SyncCryptoPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /**
     * Fetch and store a price snapshot for every active crypto asset.
     *
     * @param  CryptoPriceFetcher  $fetcher  The provider used to resolve the spot prices.
     * @return void This job handler does not return a value.
     */
    public function handle(CryptoPriceFetcher $fetcher): void
    {
        $quoteCurrency = (string) config('services.coingecko.quote_currency', 'EUR');
        $assets = CryptoAsset::query()->active()->get();

        if ($assets->isEmpty()) {
            return;
        }

        $prices = $fetcher->fetchPrices($assets->pluck('coingecko_id')->all(), $quoteCurrency);

        if ($prices === []) {
            return;
        }

        $fetchedAt = now();

        foreach ($assets as $asset) {
            $price = $prices[$asset->coingecko_id] ?? null;

            if ($price === null) {
                continue;
            }

            CryptoPrice::updateOrCreate(
                [
                    'crypto_asset_id' => $asset->id,
                    'quote_currency' => $quoteCurrency,
                    'fetched_at' => $fetchedAt,
                ],
                ['price' => $price],
            );
        }
    }
}
