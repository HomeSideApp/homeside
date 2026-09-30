<?php

namespace App\Data\Economy;

use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use Illuminate\Support\Carbon;

/**
 * Data for confirming an annotated economic import.
 *
 * Carries the user-edited transaction details before the import is persisted,
 * converting monetary amounts from decimal to minor units.
 */
final readonly class ConfirmEconomicImportData
{
    /**
     * Create an immutable import confirmation data object.
     *
     * @param  TransactionType|null  $type  The reviewed transaction type, when supplied.
     * @param  TransactionScope|null  $scope  The reviewed transaction scope, when supplied.
     * @param  string|null  $title  The reviewed transaction title, when supplied.
     * @param  int|null  $amount_minor  The reviewed amount in minor currency units, when supplied.
     * @param  string|null  $currency  The reviewed ISO currency code, when supplied.
     * @param  string|null  $account_id  The reviewed private account identifier, or null when none applies.
     * @param  string|null  $place  The reviewed place, including null when explicitly cleared.
     * @param  Carbon|null  $occurred_at  The reviewed occurrence timestamp, including null when explicitly cleared.
     * @param  array<int, array<string, mixed>>|null  $items  The reviewed item collection, when supplied.
     * @param  array<int, array<string, mixed>>|null  $taxes  The reviewed tax collection, when supplied.
     * @param  array<int, array<string, mixed>>|null  $participants  The reviewed participant collection, when supplied.
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
        public ?array $items,
        public ?array $taxes,
        public ?array $participants,
        public array $provided_fields = [],
    ) {}

    /**
     * Build import confirmation data from a validated review payload.
     *
     * @param  array<string, mixed>  $data  The validated review values keyed by request field.
     * @return self The normalized immutable import confirmation data.
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
            items: $data['items'] ?? null,
            taxes: $data['taxes'] ?? null,
            participants: $data['participants'] ?? null,
            provided_fields: array_map(
                static fn (string $field): string => $field === 'amount_minor' ? 'amount' : $field,
                array_keys($data),
            ),
        );
    }

    /**
     * Transform the reviewed values into their serializable persistence representation.
     *
     * @return array<string, mixed> The reviewed import fields using decimal monetary values.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'scope' => $this->scope?->value,
            'title' => $this->title,
            'amount' => $this->amount_minor !== null ? number_format($this->amount_minor / 100, 2, '.', '') : null,
            'currency' => $this->currency,
            'account_id' => $this->account_id,
            'place' => $this->place,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'items' => $this->items,
            'taxes' => $this->taxes,
            'participants' => $this->participants,
        ];
    }

    /**
     * Determine whether a field was explicitly supplied in the review payload.
     *
     * @param  string  $field  The review field name to inspect.
     * @return bool True when the field was supplied, including with a null value; otherwise false.
     */
    public function has(string $field): bool
    {
        return in_array($field, $this->provided_fields, true);
    }
}
