<?php

namespace App\Actions\Economy;

use App\Data\Economy\CreateEconomicTransactionData;
use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionScope;
use App\Models\Contact;
use App\Models\EconomicAccount;
use App\Models\EconomicDocument;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an economic transaction with its items, taxes and participants,
 * enforcing scope and participant membership rules.
 */
final class CreateEconomicTransaction
{
    /**
     * Create a new transaction creation action instance.
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
     * Create an economic transaction and all of its item, tax, and participant records.
     *
     * @param  CreateEconomicTransactionData  $data  The validated transaction data to persist.
     * @param  Household|null  $household  The household scope, or null for a private transaction.
     * @param  User  $user  The user creating and owning the transaction.
     * @return EconomicTransaction The created transaction with its item, tax, and participant relations loaded.
     */
    public function execute(CreateEconomicTransactionData $data, ?Household $household, User $user): EconomicTransaction
    {
        if ($data->recurrence_frequency !== null && $data->occurred_at === null) {
            throw ValidationException::withMessages([
                'occurred_at' => __('app.validation.recurrence_requires_date'),
            ]);
        }

        if ($data->recurrence_frequency === RecurrenceFrequency::Weekly
            && $data->recurrence_weekdays === []) {
            throw ValidationException::withMessages([
                'recurrence_weekdays' => __('app.validation.recurrence_weekdays_required'),
            ]);
        }

        if ($household === null && $data->scope === TransactionScope::Shared) {
            throw ValidationException::withMessages([
                'scope' => __('app.validation.private_cannot_share'),
            ]);
        }

        if ($household !== null && $data->scope === TransactionScope::Shared) {
            if ($data->participants === []) {
                throw ValidationException::withMessages([
                    'participants' => __('app.validation.shared_requires_participant'),
                ]);
            }

            $memberIds = $household->members()->pluck('id')->all();

            foreach ($data->participants as $participantData) {
                if (! in_array($participantData->household_member_id, $memberIds, true)) {
                    throw ValidationException::withMessages([
                        'participants' => __('app.errors.participant_outside_household'),
                    ]);
                }
            }
        }

        if ($data->account_id !== null) {
            $accountIsOwned = EconomicAccount::query()
                ->whereKey($data->account_id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $accountIsOwned) {
                throw ValidationException::withMessages([
                    'account_id' => __('app.validation.account_not_owned'),
                ]);
            }
        }

        if ($data->source_document_id !== null) {
            $documentIsAccessible = EconomicDocument::query()
                ->whereKey($data->source_document_id)
                ->where('household_id', $household?->id)
                ->when($household === null, fn ($query) => $query->where('uploaded_by', $user->id))
                ->exists();

            if (! $documentIsAccessible) {
                throw ValidationException::withMessages([
                    'source_document_id' => __('app.errors.economic_document_wrong_scope'),
                ]);
            }
        }

        if ($data->contact_id !== null) {
            $contactIsVisible = Contact::query()
                ->visibleTo($user)
                ->whereKey($data->contact_id)
                ->exists();

            if (! $contactIsVisible) {
                throw ValidationException::withMessages([
                    'contact_id' => __('app.errors.contact_not_available'),
                ]);
            }
        }

        $participants = $data->scope === TransactionScope::Shared
            ? $this->calculateTransactionShares->execute($data->amount_minor, $data->participants)
            : [];
        $recurrenceNextAt = $data->recurrence_frequency === null
            ? null
            : $this->calculateNextRecurrence->execute(
                $data->occurred_at,
                $data->occurred_at,
                $data->recurrence_frequency,
                $data->recurrence_interval,
                $data->recurrence_weekdays,
            );

        if ($recurrenceNextAt !== null
            && $data->recurrence_ends_at !== null
            && $recurrenceNextAt->isAfter($data->recurrence_ends_at->copy()->endOfDay())) {
            $recurrenceNextAt = null;
        }

        return DB::transaction(function () use ($data, $household, $participants, $recurrenceNextAt, $user) {
            $transaction = EconomicTransaction::create([
                'household_id' => $household?->id,
                'created_by' => $user->id,
                'type' => $data->type,
                'scope' => $data->scope,
                'title' => $data->title,
                'amount_minor' => $data->amount_minor,
                'currency' => $data->currency,
                'account_id' => $data->account_id,
                'place' => $data->place,
                'occurred_at' => $data->occurred_at,
                'source_document_id' => $data->source_document_id,
                'notes' => $data->notes,
                'recurrence_frequency' => $data->recurrence_frequency,
                'recurrence_interval' => $data->recurrence_frequency === null
                    ? null
                    : $data->recurrence_interval,
                'recurrence_weekdays' => $data->recurrence_frequency === RecurrenceFrequency::Weekly
                    ? $data->recurrence_weekdays
                    : null,
                'recurrence_ends_at' => $data->recurrence_frequency === null
                    ? null
                    : $data->recurrence_ends_at,
                'recurrence_next_at' => $recurrenceNextAt,
            ]);

            foreach ($data->items as $index => $itemData) {
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

            foreach ($data->taxes as $taxData) {
                $transaction->taxes()->create([
                    'name' => $taxData->name,
                    'rate' => $taxData->rate,
                    'taxable_base_minor' => $taxData->taxable_base_minor,
                    'tax_amount_minor' => $taxData->tax_amount_minor,
                ]);
            }

            if ($data->scope === TransactionScope::Shared) {
                foreach ($participants as $participant) {
                    $transaction->participants()->create($participant);
                }
            }

            if ($data->contact_id !== null) {
                $transaction->contacts()->attach($data->contact_id, ['role' => 'related']);
            }

            return $transaction->load(['items', 'taxes', 'participants', 'contacts']);
        });
    }
}
