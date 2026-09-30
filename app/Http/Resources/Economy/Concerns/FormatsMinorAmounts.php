<?php

namespace App\Http\Resources\Economy\Concerns;

use App\Models\EconomicAccount;

/**
 * Formats minor-unit amounts into the decimal strings expected by the clients.
 *
 * The economic domain stores every amount as an integer in minor units, while the web pages and
 * the mobile contract consume decimal strings. The precision is taken from the account that funded
 * the transaction, because a crypto account stores satoshis and formatting it with two decimals
 * would silently lose the real value.
 */
trait FormatsMinorAmounts
{
    /**
     * Format a minor-unit amount using the precision of the owning transaction account.
     *
     * @param  int|null  $minor  The amount in minor units, or null when the value is absent.
     * @return string|null The formatted amount, or null when the input was null.
     */
    protected function formatMinor(?int $minor): ?string
    {
        if ($minor === null) {
            return null;
        }

        $decimalPlaces = $this->resolveDecimalPlaces();

        return number_format(
            $minor / (10 ** $decimalPlaces),
            $decimalPlaces,
            '.',
            '',
        );
    }

    /**
     * Resolve the decimal precision of the account linked to the parent transaction.
     *
     * Falls back to two decimals when the account is not loaded or the movement has no account,
     * which keeps the fiat behaviour intact.
     *
     * @return int The number of minor units per currency unit.
     */
    protected function resolveDecimalPlaces(): int
    {
        $transaction = $this->resource->transaction ?? null;

        if ($transaction === null || ! $transaction->relationLoaded('account')) {
            return 2;
        }

        $account = $transaction->getRelation('account');

        return $account instanceof EconomicAccount
            ? max((int) $account->decimal_places, 0)
            : 2;
    }
}
