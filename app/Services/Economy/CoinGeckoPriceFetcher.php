<?php

namespace App\Services\Economy;

use App\Contracts\CryptoPriceFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves crypto spot prices from the public CoinGecko API.
 */
final class CoinGeckoPriceFetcher implements CryptoPriceFetcher
{
    /**
     * Fetch spot prices from CoinGecko in a single batched request.
     *
     * A provider outage or a rate limit returns an empty array instead of throwing, because the
     * valuation must degrade to cached data rather than break the user request.
     *
     * @param  array<int, string>  $providerIds  The CoinGecko identifiers to resolve.
     * @param  string  $quoteCurrency  The ISO currency code used as quote.
     * @return array<string, float> The spot prices keyed by CoinGecko identifier.
     */
    public function fetchPrices(array $providerIds, string $quoteCurrency): array
    {
        if ($providerIds === []) {
            return [];
        }

        try {
            $response = Http::baseUrl((string) config('services.coingecko.base_url'))
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get('/api/v3/simple/price', [
                    'ids' => implode(',', $providerIds),
                    'vs_currencies' => strtolower($quoteCurrency),
                ]);

            if (! $response->successful()) {
                Log::warning('CoinGecko price request failed', ['status' => $response->status()]);

                return [];
            }

            $payload = $response->json();
            $quote = strtolower($quoteCurrency);
            $prices = [];

            foreach ($providerIds as $providerId) {
                $value = $payload[$providerId][$quote] ?? null;

                if (is_numeric($value)) {
                    $prices[$providerId] = (float) $value;
                }
            }

            return $prices;
        } catch (\Throwable $exception) {
            Log::warning('CoinGecko price request threw', ['message' => $exception->getMessage()]);

            return [];
        }
    }
}
