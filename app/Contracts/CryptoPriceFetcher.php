<?php

namespace App\Contracts;

/**
 * Contract for resolving crypto spot prices from an external market provider.
 */
interface CryptoPriceFetcher
{
    /**
     * Fetch the current spot prices for the given provider asset identifiers.
     *
     * Implementations must never throw: a provider outage returns an empty array so the caller can
     * keep serving cached values.
     *
     * @param  array<int, string>  $providerIds  The provider identifiers keyed by position.
     * @param  string  $quoteCurrency  The ISO currency code used as quote.
     * @return array<string, float> The spot prices keyed by provider identifier.
     */
    public function fetchPrices(array $providerIds, string $quoteCurrency): array;
}
