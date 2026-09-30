<?php

namespace App\Data\Economy;

use Illuminate\Support\Carbon;

/**
 * Class EconomicAccountData
 *
 * Immutable payload used to create or update a private economic account, including its
 * opening balance and the payment method it is bound to.
 */
final readonly class EconomicAccountData
{
    /**
     * Create immutable data for an economic account.
     *
     * @param  array<int, string>  $payment_method_ids  The payment method identifiers selected by the user.
     * @param  string  $name  The user-facing account name.
     * @param  string  $currency  The uppercase currency code or crypto symbol.
     * @param  string|null  $crypto_asset_id  The crypto asset identifier when the account is a crypto wallet.
     * @param  int  $decimal_places  The number of minor units per currency unit.
     * @param  int  $initial_balance_minor  The opening balance in minor units, possibly negative.
     * @param  Carbon|null  $initial_balance_at  The optional date the opening balance refers to.
     * @param  string|null  $icon  The optional Lucide icon name.
     * @param  string|null  $color  The optional badge color in hexadecimal notation.
     * @param  string|null  $last_four_digits  The optional last four digits of the account number.
     * @param  bool  $include_in_totals  Whether the account participates in aggregated totals.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public array $payment_method_ids,
        public string $name,
        public string $currency,
        public ?string $crypto_asset_id = null,
        public int $decimal_places = 2,
        public int $initial_balance_minor = 0,
        public ?Carbon $initial_balance_at = null,
        public ?string $icon = null,
        public ?string $color = null,
        public ?string $last_four_digits = null,
        public bool $include_in_totals = true,
    ) {}

    /**
     * Build account data from a validated request payload.
     *
     * The opening balance is accepted either already expressed in minor units
     * (`initial_balance_minor`) or as a decimal string (`initial_balance`), which is scaled
     * using the account decimal precision.
     *
     * @param  array<string, mixed>  $data  The validated values keyed by request field.
     * @return self The normalized immutable account data.
     */
    public static function fromArray(array $data): self
    {
        $decimalPlaces = (int) ($data['decimal_places'] ?? 2);

        $initialBalanceMinor = isset($data['initial_balance_minor'])
            ? (int) $data['initial_balance_minor']
            : self::scaleAmount($data['initial_balance'] ?? 0, $decimalPlaces);

        return new self(
            payment_method_ids: array_values(array_map(
                static fn (mixed $id): string => (string) $id,
                (array) ($data['payment_method_ids'] ?? []),
            )),
            name: $data['name'],
            currency: strtoupper((string) $data['currency']),
            crypto_asset_id: $data['crypto_asset_id'] ?? null,
            decimal_places: $decimalPlaces,
            initial_balance_minor: $initialBalanceMinor,
            initial_balance_at: isset($data['initial_balance_at'])
                ? Carbon::parse($data['initial_balance_at'])
                : null,
            icon: $data['icon'] ?? null,
            color: $data['color'] ?? null,
            last_four_digits: $data['last_four_digits'] ?? null,
            include_in_totals: filter_var($data['include_in_totals'] ?? true, FILTER_VALIDATE_BOOL),
        );
    }

    /**
     * Export the data as an attribute array for persistence.
     *
     * @param  string  $userId  The owner identifier of the account.
     * @return array<string, mixed> The persistable attribute array.
     */
    public function toAttributes(string $userId): array
    {
        return [
            'user_id' => $userId,
            'name' => $this->name,
            'currency' => $this->currency,
            'crypto_asset_id' => $this->crypto_asset_id,
            'decimal_places' => $this->decimal_places,
            'initial_balance_minor' => $this->initial_balance_minor,
            'initial_balance_at' => $this->initial_balance_at,
            'icon' => $this->icon,
            'color' => $this->color ?? '#6366F1',
            'last_four_digits' => $this->last_four_digits,
            'include_in_totals' => $this->include_in_totals,
        ];
    }

    /**
     * Scale a decimal amount string into minor units.
     *
     * @param  string|int|float  $amount  The decimal amount supplied by the user.
     * @param  int  $decimalPlaces  The number of minor units per currency unit.
     * @return int The amount expressed in minor units.
     */
    private static function scaleAmount(string|int|float $amount, int $decimalPlaces): int
    {
        return (int) round(((float) $amount) * (10 ** $decimalPlaces));
    }
}
