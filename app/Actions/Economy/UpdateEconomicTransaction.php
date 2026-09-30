<?php

namespace App\Actions\Economy;

use App\Data\Economy\EconomicTransactionParticipantData;
use App\Data\Economy\UpdateEconomicTransactionData;
use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionScope;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Updates an economic transaction, replacing its items, taxes and
 * participants when provided.
 */
final class UpdateEconomicTransaction
{
    /**
     * Create a new transaction update action instance.
     *
     * @param  CalculateTransactionShares  $calculateTransactionShares  The service that validates and normalizes participant shares.
     * @param  CalculateNextRecurrence  $calculateNextRecurrence  The service that calculates the next scheduled occurrence.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        private readonly CalculateTransactionShares $calculateTransactionShares,
        private readonly CalculateNextRecurrence $calculateNextRecurrence,
    ) {}

    /**
     * Update an economic transaction and replace any supplied child collections.
     *
     * @param  EconomicTransaction  $transaction  The existing transaction to update.
     * @param  UpdateEconomicTransactionData  $data  The validated values and optional child collections to apply.
     * @return EconomicTransaction The refreshed transaction with all economic relations loaded.
     */
    public function execute(EconomicTransaction $transaction, UpdateEconomicTransactionData $data): EconomicTransaction
    {
        $scope = $data->scope ?? $transaction->scope;
        $amountMinor = $data->amount_minor ?? $transaction->amount_minor;
        $participantData = $data->participants;

        if ($transaction->household_id === null && $scope === TransactionScope::Shared) {
            throw ValidationException::withMessages([
                'scope' => __('app.validation.private_cannot_share'),
            ]);
        }

        if ($scope === TransactionScope::Shared) {
            $participantData ??= $transaction->participants->map(
                fn ($participant): EconomicTransactionParticipantData => new EconomicTransactionParticipantData(
                    household_member_id: $participant->household_member_id,
                    split_type: $participant->split_type,
                    amount_minor: $participant->amount_minor,
                    percentage: $participant->percentage,
                )
            )->all();

            if ($participantData === []) {
                throw ValidationException::withMessages([
                    'participants' => __('app.validation.shared_requires_participant'),
                ]);
            }

            $memberIds = $transaction->household?->members()->pluck('id')->all() ?? [];
            foreach ($participantData as $participant) {
                if (! in_array($participant->household_member_id, $memberIds, true)) {
                    throw ValidationException::withMessages([
                        'participants' => __('app.validation.participants_belong_to_household'),
                    ]);
                }
            }
        }

        if ($data->has('account_id') && $data->account_id !== null) {
            $accountIsOwned = EconomicAccount::query()
                ->whereKey($data->account_id)
                ->where('user_id', $transaction->created_by)
                ->exists();

            if (! $accountIsOwned) {
                throw ValidationException::withMessages([
                    'account_id' => __('app.validation.account_not_owned'),
                ]);
            }
        }

        $participants = $scope === TransactionScope::Shared
            ? $this->calculateTransactionShares->execute($amountMinor, $participantData)
            : [];

        return DB::transaction(function () use ($transaction, $data, $participants) {
            $updates = [];
            foreach ([
                'type' => ['type', $data->type],
                'scope' => ['scope', $data->scope],
                'title' => ['title', $data->title],
                'amount_minor' => ['amount', $data->amount_minor],
                'currency' => ['currency', $data->currency],
                'account_id' => ['account_id', $data->account_id],
                'place' => ['place', $data->place],
                'occurred_at' => ['occurred_at', $data->occurred_at],
                'notes' => ['notes', $data->notes],
            ] as $attribute => [$field, $value]) {
                if ($data->has($field)) {
                    $updates[$attribute] = $value;
                }
            }

            $updates = [...$updates, ...$this->buildRecurrenceUpdates($transaction, $data)];

            $transaction->update($updates);

            if ($data->has('items')) {
                $transaction->items()->delete();
                foreach ($data->items ?? [] as $index => $itemData) {
                    $transaction->items()->create([
                        'name' => $itemData->name,
                        'quantity' => $itemData->quantity,
                        'unit_amount_minor' => $itemData->unit_amount_minor,
                        'subtotal_minor' => $itemData->subtotal_minor,
                        'tax_amount_minor' => $itemData->tax_amount_minor,
                        'total_minor' => $itemData->total_minor,
                        'position' => $index + 1,
                    ]);
                }
            }

            if ($data->has('taxes')) {
                $transaction->taxes()->delete();
                foreach ($data->taxes ?? [] as $taxData) {
                    $transaction->taxes()->create([
                        'name' => $taxData->name,
                        'rate' => $taxData->rate,
                        'taxable_base_minor' => $taxData->taxable_base_minor,
                        'tax_amount_minor' => $taxData->tax_amount_minor,
                    ]);
                }
            }

            if ($data->has('participants') || $data->has('scope') || $data->has('amount')) {
                $transaction->participants()->delete();
                foreach ($participants as $participant) {
                    $transaction->participants()->create($participant);
                }
            }

            if ($data->has('contact_id')) {
                $this->syncRelatedContact($transaction, $data->contact_id);
            }

            return $transaction->refresh()->load(['items', 'taxes', 'participants', 'contacts']);
        });
    }

    /**
     * Replace the optional contact linked to the transaction.
     *
     * Only contacts with the "related" role are managed here so other pivot roles stay untouched.
     *
     * @param  EconomicTransaction  $transaction  The transaction whose linked contact changes.
     * @param  string|null  $contactId  The contact identifier to link, or null to unlink.
     * @return void This method does not return a value.
     */
    private function syncRelatedContact(EconomicTransaction $transaction, ?string $contactId): void
    {
        $transaction->contacts()->wherePivot('role', 'related')->detach();

        if ($contactId !== null) {
            $transaction->contacts()->attach($contactId, ['role' => 'related']);
        }
    }

    /**
     * Build recurrence column updates for a source transaction.
     *
     * Generated occurrences ignore empty recurrence form fields so that editing one occurrence
     * never changes or creates a recurrence series.
     *
     * @param  EconomicTransaction  $transaction  The transaction whose recurrence may be updated.
     * @param  UpdateEconomicTransactionData  $data  The normalized update values and field-presence metadata.
     * @return array<string, mixed> The recurrence attributes that should be persisted.
     */
    private function buildRecurrenceUpdates(
        EconomicTransaction $transaction,
        UpdateEconomicTransactionData $data,
    ): array {
        if ($transaction->recurrence_parent_id !== null) {
            return [];
        }

        $recurrenceWasProvided = $data->has('recurrence_frequency')
            || $data->has('recurrence_interval')
            || $data->has('recurrence_weekdays')
            || $data->has('recurrence_ends_at');
        $dateChangedForRecurringTransaction = $data->has('occurred_at')
            && $transaction->recurrence_frequency !== null;

        if (! $recurrenceWasProvided && ! $dateChangedForRecurringTransaction) {
            return [];
        }

        $frequency = $data->has('recurrence_frequency')
            ? $data->recurrence_frequency
            : $transaction->recurrence_frequency;

        if ($frequency === null) {
            return [
                'recurrence_frequency' => null,
                'recurrence_interval' => null,
                'recurrence_weekdays' => null,
                'recurrence_ends_at' => null,
                'recurrence_next_at' => null,
            ];
        }

        $occurredAt = $data->has('occurred_at')
            ? $data->occurred_at
            : $transaction->occurred_at;

        if ($occurredAt === null) {
            throw ValidationException::withMessages([
                'occurred_at' => __('app.validation.recurrence_requires_date'),
            ]);
        }

        $interval = $data->has('recurrence_interval')
            ? ($data->recurrence_interval ?? 1)
            : ($transaction->recurrence_interval ?? 1);
        $weekdays = $data->has('recurrence_weekdays')
            ? ($data->recurrence_weekdays ?? [])
            : ($transaction->recurrence_weekdays ?? []);
        $endsAt = $data->has('recurrence_ends_at')
            ? $data->recurrence_ends_at
            : $transaction->recurrence_ends_at;

        if ($frequency === RecurrenceFrequency::Weekly
            && $weekdays === []
            && ($data->has('recurrence_frequency') || $data->has('recurrence_weekdays'))) {
            throw ValidationException::withMessages([
                'recurrence_weekdays' => __('app.validation.recurrence_weekdays_required'),
            ]);
        }

        if ($endsAt !== null && $endsAt->isBefore($occurredAt->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'recurrence_ends_at' => __('app.validation.recurrence_end_before_start'),
            ]);
        }

        $latestGeneratedAt = $transaction->generatedOccurrences()->max('occurred_at');
        $cursor = $latestGeneratedAt === null
            ? $occurredAt
            : Carbon::parse($latestGeneratedAt)->max($occurredAt);
        $nextAt = $this->calculateNextRecurrence->execute(
            $occurredAt,
            $cursor,
            $frequency,
            $interval,
            $weekdays,
        );

        if ($endsAt !== null && $nextAt->isAfter($endsAt->copy()->endOfDay())) {
            $nextAt = null;
        }

        return [
            'recurrence_frequency' => $frequency,
            'recurrence_interval' => $interval,
            'recurrence_weekdays' => $frequency === RecurrenceFrequency::Weekly
                ? $weekdays
                : null,
            'recurrence_ends_at' => $endsAt,
            'recurrence_next_at' => $nextAt,
        ];
    }
}
