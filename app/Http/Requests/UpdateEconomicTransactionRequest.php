<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Models\EconomicTransaction;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEconomicTransactionRequest extends FormRequest
{
    /**
     * Resolve the transaction route parameter before request authorization completes.
     *
     * @return EconomicTransaction The transaction being updated.
     */
    private function resolveTransaction(): EconomicTransaction
    {
        $param = $this->route('transaction');

        return $param instanceof EconomicTransaction
            ? $param
            : EconomicTransaction::query()->whereKey($param)->firstOrFail();
    }

    /**
     * Resolve the transaction household without requiring a populated route.
     *
     * @return string|null The transaction household identifier, when available.
     */
    private function resolveTransactionHouseholdId(): ?string
    {
        $transaction = $this->route('transaction');

        if ($transaction instanceof EconomicTransaction) {
            return $transaction->household_id;
        }

        return is_string($transaction)
            ? EconomicTransaction::query()->whereKey($transaction)->value('household_id')
            : null;
    }

    /**
     * Determine whether the authenticated user may update the routed transaction.
     *
     * @return bool True when the transaction policy authorizes the update; otherwise false.
     */
    public function authorize(): bool
    {
        $transaction = $this->resolveTransaction();

        return $transaction->household_id === $this->resolveHousehold()?->id
            && ($this->user()?->can('update', $transaction) ?? false);
    }

    /**
     * Resolve the optional household route parameter before request authorization completes.
     *
     * @return Household|null The routed household, or null for a private economy route.
     */
    private function resolveHousehold(): ?Household
    {
        $household = $this->route('household');

        if ($household instanceof Household) {
            return $household;
        }

        return is_string($household)
            ? Household::query()->whereKey($household)->firstOrFail()
            : null;
    }

    /**
     * Define validation callbacks that enforce transaction scope invariants.
     *
     * @return array<int, callable(Validator): void> The callbacks executed after the base validation rules.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $transaction = $this->resolveTransaction();

            if ($this->input('scope') === 'shared' && $transaction->household_id === null) {
                $validator->errors()->add('scope', __('app.validation.private_cannot_share'));
            }

            $frequency = $this->exists('recurrence_frequency')
                ? $this->input('recurrence_frequency')
                : $transaction->recurrence_frequency?->value;
            $occurredAt = $this->exists('occurred_at')
                ? $this->input('occurred_at')
                : $transaction->occurred_at;

            if ($frequency && ! $occurredAt) {
                $validator->errors()->add('occurred_at', __('app.validation.recurrence_requires_date'));
            }

            if ($frequency === RecurrenceFrequency::Weekly->value
                && $this->exists('recurrence_weekdays')
                && count((array) $this->input('recurrence_weekdays', [])) === 0) {
                $validator->errors()->add(
                    'recurrence_weekdays',
                    __('app.validation.recurrence_weekdays_required'),
                );
            }

            if ($this->input('recurrence_frequency') === RecurrenceFrequency::Weekly->value
                && ! $this->exists('recurrence_weekdays')) {
                $validator->errors()->add(
                    'recurrence_weekdays',
                    __('app.validation.recurrence_weekdays_required'),
                );
            }

            if ($transaction->recurrence_parent_id !== null && $this->filled('recurrence_frequency')) {
                $validator->errors()->add(
                    'recurrence_frequency',
                    __('app.validation.generated_occurrence_cannot_recur'),
                );
            }
        }];
    }

    /**
     * Define validation rules for updating an economic transaction and its child records.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        $householdId = $this->resolveTransactionHouseholdId();

        return [
            'type' => ['sometimes', 'string', 'in:expense,income'],
            'scope' => ['sometimes', 'string', 'in:personal,shared'],
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9]{2,10}$/'],
            'account_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('economic_accounts', 'id')->where('user_id', $this->user()?->id)],
            'place' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_weekdays' => ['nullable', 'array', 'max:7'],
            'recurrence_weekdays.*' => ['integer', 'min:1', 'max:7', 'distinct:strict'],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'contact_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('contacts', 'id')->where(
                fn ($query) => $query
                    ->where('user_id', $this->user()?->id)
                    ->orWhereIn('household_id', DB::table('household_members')->where('user_id', $this->user()?->id)->select('household_id'))
            )],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['required_with:items', 'numeric', 'min:0'],
            'taxes' => ['nullable', 'array'],
            'taxes.*.name' => ['required_with:taxes', 'string', 'max:255'],
            'taxes.*.rate' => ['required_with:taxes', 'numeric', 'min:0', 'max:100'],
            'taxes.*.taxable_base' => ['required_with:taxes', 'numeric', 'min:0'],
            'taxes.*.amount' => ['required_with:taxes', 'numeric', 'min:0'],
            'participants' => ['nullable', 'array'],
            'participants.*.household_member_id' => ['required_with:participants', 'string', 'distinct', Rule::exists('household_members', 'id')->where('household_id', $householdId)],
            'participants.*.split_type' => ['required_with:participants', 'string', 'in:equal,fixed,percentage'],
            'participants.*.amount' => ['nullable', 'numeric', 'min:0'],
            'participants.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
