<?php

namespace App\Data\Economy;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use Illuminate\Support\Carbon;

final readonly class CreateEconomicTransactionData
{
    /**
     * Create immutable data for a new economic transaction.
     *
     * @param  TransactionType  $type  The transaction type being created.
     * @param  TransactionScope  $scope  The ownership and sharing scope.
     * @param  string  $title  The user-facing transaction title.
     * @param  int  $amount_minor  The amount in minor currency units.
     * @param  string  $currency  The ISO currency code.
     * @param  string|null  $account_id  The optional private account that funded the transaction.
     * @param  string|null  $place  The optional merchant or place.
     * @param  Carbon|null  $occurred_at  The optional transaction occurrence timestamp.
     * @param  string|null  $notes  The optional transaction notes.
     * @param  string|null  $source_document_id  The optional source document identifier.
     * @param  EconomicTransactionItemData[]  $items  The transaction line items.
     * @param  EconomicTransactionTaxData[]  $taxes  The transaction tax breakdown.
     * @param  EconomicTransactionParticipantData[]  $participants  The shared transaction participants.
     * @param  RecurrenceFrequency|null  $recurrence_frequency  The optional recurrence frequency.
     * @param  int  $recurrence_interval  The positive number of frequency units between occurrences.
     * @param  array<int, int>  $recurrence_weekdays  The selected ISO weekdays for a weekly recurrence.
     * @param  Carbon|null  $recurrence_ends_at  The optional inclusive final recurrence date.
     * @param  string|null  $contact_id  The optional contact linked to the transaction for context.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public TransactionType $type,
        public TransactionScope $scope,
        public string $title,
        public int $amount_minor,
        public string $currency,
        public ?string $account_id,
        public ?string $place,
        public ?Carbon $occurred_at,
        public ?string $notes,
        public ?string $source_document_id,
        public array $items,
        public array $taxes,
        public array $participants,
        public ?RecurrenceFrequency $recurrence_frequency = null,
        public int $recurrence_interval = 1,
        public array $recurrence_weekdays = [],
        public ?Carbon $recurrence_ends_at = null,
        public ?string $contact_id = null,
    ) {}

    /**
     * Build transaction creation data from a validated request payload.
     *
     * @param  array<string, mixed>  $data  The validated creation values keyed by request field.
     * @return self The normalized immutable transaction creation data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: TransactionType::from($data['type']),
            scope: TransactionScope::from($data['scope']),
            title: $data['title'],
            amount_minor: isset($data['amount_minor'])
                ? (int) $data['amount_minor']
                : (int) round($data['amount'] * 100),
            currency: $data['currency'] ?? 'EUR',
            account_id: $data['account_id'] ?? null,
            place: $data['place'] ?? null,
            occurred_at: isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
            notes: $data['notes'] ?? null,
            source_document_id: $data['source_document_id'] ?? null,
            items: array_map(
                fn (array $item) => EconomicTransactionItemData::fromArray($item),
                $data['items'] ?? []
            ),
            taxes: array_map(
                fn (array $tax) => EconomicTransactionTaxData::fromArray($tax),
                $data['taxes'] ?? []
            ),
            participants: array_map(
                fn (array $p) => EconomicTransactionParticipantData::fromArray($p),
                $data['participants'] ?? []
            ),
            recurrence_frequency: isset($data['recurrence_frequency'])
                ? RecurrenceFrequency::from($data['recurrence_frequency'])
                : null,
            recurrence_interval: (int) ($data['recurrence_interval'] ?? 1),
            recurrence_weekdays: array_map(
                static fn (mixed $weekday): int => (int) $weekday,
                $data['recurrence_weekdays'] ?? [],
            ),
            recurrence_ends_at: isset($data['recurrence_ends_at'])
                ? Carbon::parse($data['recurrence_ends_at'])
                : null,
            contact_id: $data['contact_id'] ?? null,
        );
    }
}
