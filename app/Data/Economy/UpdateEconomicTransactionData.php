<?php

namespace App\Data\Economy;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use Illuminate\Support\Carbon;

final readonly class UpdateEconomicTransactionData
{
    /**
     * Create an immutable transaction update data object.
     *
     * @param  TransactionType|null  $type  The replacement transaction type, when supplied.
     * @param  TransactionScope|null  $scope  The replacement transaction scope, when supplied.
     * @param  string|null  $title  The replacement transaction title, when supplied.
     * @param  int|null  $amount_minor  The replacement amount in minor currency units, when supplied.
     * @param  string|null  $currency  The replacement ISO currency code, when supplied.
     * @param  string|null  $account_id  The replacement account identifier, including null when explicitly cleared.
     * @param  string|null  $place  The replacement place, including null when explicitly cleared.
     * @param  Carbon|null  $occurred_at  The replacement occurrence timestamp, including null when explicitly cleared.
     * @param  string|null  $notes  The replacement notes, including null when explicitly cleared.
     * @param  EconomicTransactionItemData[]|null  $items  The replacement item collection, when supplied.
     * @param  EconomicTransactionTaxData[]|null  $taxes  The replacement tax collection, when supplied.
     * @param  EconomicTransactionParticipantData[]|null  $participants  The replacement participant collection, when supplied.
     * @param  RecurrenceFrequency|null  $recurrence_frequency  The replacement recurrence frequency, including null when disabled.
     * @param  int|null  $recurrence_interval  The replacement number of frequency units between occurrences.
     * @param  array<int, int>|null  $recurrence_weekdays  The replacement ISO weekdays for a weekly recurrence.
     * @param  Carbon|null  $recurrence_ends_at  The replacement inclusive final recurrence date.
     * @param  string|null  $contact_id  The replacement linked contact, including null when explicitly cleared.
     * @param  string[]  $provided_fields  The original request fields used to distinguish omitted and cleared values.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public ?TransactionType $type,
        public ?TransactionScope $scope,
        public ?string $title,
        public ?int $amount_minor,
        public ?string $currency,
        public ?string $account_id,
        public ?string $place,
        public ?Carbon $occurred_at,
        public ?string $notes,
        public ?array $items,
        public ?array $taxes,
        public ?array $participants,
        public ?RecurrenceFrequency $recurrence_frequency,
        public ?int $recurrence_interval,
        public ?array $recurrence_weekdays,
        public ?Carbon $recurrence_ends_at,
        public ?string $contact_id = null,
        public array $provided_fields = [],
    ) {}

    /**
     * Build transaction update data from a validated request payload.
     *
     * @param  array<string, mixed>  $data  The validated update values keyed by request field.
     * @return self The normalized immutable transaction update data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: isset($data['type']) ? TransactionType::from($data['type']) : null,
            scope: isset($data['scope']) ? TransactionScope::from($data['scope']) : null,
            title: $data['title'] ?? null,
            amount_minor: isset($data['amount_minor'])
                ? (int) $data['amount_minor']
                : (isset($data['amount']) ? (int) round($data['amount'] * 100) : null),
            currency: $data['currency'] ?? null,
            account_id: $data['account_id'] ?? null,
            place: $data['place'] ?? null,
            occurred_at: isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
            notes: $data['notes'] ?? null,
            items: isset($data['items'])
                ? array_map(fn (array $item) => EconomicTransactionItemData::fromArray($item), $data['items'])
                : null,
            taxes: isset($data['taxes'])
                ? array_map(fn (array $tax) => EconomicTransactionTaxData::fromArray($tax), $data['taxes'])
                : null,
            participants: isset($data['participants'])
                ? array_map(fn (array $p) => EconomicTransactionParticipantData::fromArray($p), $data['participants'])
                : null,
            recurrence_frequency: isset($data['recurrence_frequency'])
                ? RecurrenceFrequency::from($data['recurrence_frequency'])
                : null,
            recurrence_interval: isset($data['recurrence_interval'])
                ? (int) $data['recurrence_interval']
                : null,
            recurrence_weekdays: isset($data['recurrence_weekdays'])
                ? array_map(
                    static fn (mixed $weekday): int => (int) $weekday,
                    $data['recurrence_weekdays'],
                )
                : null,
            recurrence_ends_at: isset($data['recurrence_ends_at'])
                ? Carbon::parse($data['recurrence_ends_at'])
                : null,
            contact_id: $data['contact_id'] ?? null,
            provided_fields: array_map(
                static fn (string $field): string => $field === 'amount_minor' ? 'amount' : $field,
                array_keys($data),
            ),
        );
    }

    /**
     * Determine whether a field was explicitly supplied in the update payload.
     *
     * @param  string  $field  The request field name to inspect.
     * @return bool True when the field was supplied, including with a null value; otherwise false.
     */
    public function has(string $field): bool
    {
        return in_array($field, $this->provided_fields, true);
    }
}
