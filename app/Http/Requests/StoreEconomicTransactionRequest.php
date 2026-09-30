<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEconomicTransactionRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create a transaction in the requested scope.
     *
     * @return bool True when the user is authenticated and belongs to the routed household, or when the route is private.
     */
    public function authorize(): bool
    {
        $household = $this->resolveHousehold();

        if ($household === null) {
            return $this->user() !== null;
        }

        return $this->user()?->isMemberOf($household) ?? false;
    }

    /**
     * Define validation callbacks that prevent private transactions from using shared scope.
     *
     * @return array<int, callable(Validator): void> The callbacks executed after the base validation rules.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('scope') === 'shared' && $this->resolveHousehold() === null) {
                $validator->errors()->add('scope', __('app.validation.private_cannot_share'));
            }

            if ($this->filled('recurrence_frequency') && ! $this->filled('occurred_at')) {
                $validator->errors()->add('occurred_at', __('app.validation.recurrence_requires_date'));
            }

            if ($this->input('recurrence_frequency') === RecurrenceFrequency::Weekly->value
                && count((array) $this->input('recurrence_weekdays', [])) === 0) {
                $validator->errors()->add(
                    'recurrence_weekdays',
                    __('app.validation.recurrence_weekdays_required'),
                );
            }
        }];
    }

    /**
     * Define validation rules for a new economic transaction and its child records.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        $householdParam = $this->route('household');
        $householdId = $householdParam instanceof Household ? $householdParam->id : $householdParam;
        $userId = $this->user()?->id;

        return [
            'type' => ['required', 'string', 'in:expense,income'],
            'scope' => ['required', 'string', 'in:personal,shared'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,10}$/'],
            'account_id' => ['nullable', 'uuid', Rule::exists('economic_accounts', 'id')->where('user_id', $userId)],
            'place' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['required_with:recurrence_frequency', 'nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_weekdays' => ['nullable', 'array', 'max:7'],
            'recurrence_weekdays.*' => ['integer', 'min:1', 'max:7', 'distinct:strict'],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'source_document_id' => ['nullable', 'uuid', Rule::exists('economic_documents', 'id')->where(
                fn ($query) => $householdId === null
                    ? $query->whereNull('household_id')->where('uploaded_by', $userId)
                    : $query->where('household_id', $householdId)
            )],
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where(
                fn ($query) => $query
                    ->where('user_id', $userId)
                    ->orWhereIn('household_id', DB::table('household_members')->where('user_id', $userId)->select('household_id'))
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
            'participants' => [
                Rule::excludeIf(fn (): bool => $householdId === null || $this->input('scope') !== 'shared'),
                'required',
                'array',
                'min:1',
            ],
            'participants.*.household_member_id' => ['required_with:participants', 'string', 'distinct', Rule::exists('household_members', 'id')->where('household_id', $householdId)],
            'participants.*.split_type' => ['required_with:participants', 'string', 'in:equal,fixed,percentage'],
            'participants.*.amount' => ['required_if:participants.*.split_type,fixed', 'numeric', 'min:0'],
            'participants.*.percentage' => ['required_if:participants.*.split_type,percentage', 'numeric', 'min:0', 'max:100'],
        ];
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
}
