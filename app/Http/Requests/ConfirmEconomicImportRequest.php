<?php

namespace App\Http\Requests;

use App\Models\EconomicImport;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmEconomicImportRequest extends FormRequest
{
    /**
     * Resolve the economic import route parameter before request authorization completes.
     *
     * @return EconomicImport The import being reviewed or confirmed.
     */
    private function resolveImport(): EconomicImport
    {
        $param = $this->route('import');

        return $param instanceof EconomicImport
            ? $param
            : EconomicImport::query()->whereKey($param)->firstOrFail();
    }

    /**
     * Resolve the import household without requiring a populated route.
     *
     * @return string|null The import household identifier, when available.
     */
    private function resolveImportHouseholdId(): ?string
    {
        $import = $this->route('import');

        if ($import instanceof EconomicImport) {
            return $import->household_id;
        }

        return is_string($import)
            ? EconomicImport::query()->whereKey($import)->value('household_id')
            : null;
    }

    /**
     * Determine whether the authenticated user may confirm the routed import.
     *
     * @return bool True when the import policy authorizes confirmation; otherwise false.
     */
    public function authorize(): bool
    {
        $import = $this->resolveImport();

        return $import->household_id === $this->resolveHousehold()?->id
            && ($this->user()?->can('confirm', $import) ?? false);
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
     * Define validation callbacks that prevent private imports from becoming shared transactions.
     *
     * @return array<int, callable(Validator): void> The callbacks executed after the base validation rules.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('scope') === 'shared' && $this->resolveImport()->household_id === null) {
                $validator->errors()->add('scope', __('app.validation.private_cannot_share'));
            }
        }];
    }

    /**
     * Define validation rules for the editable transaction draft produced by an import.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by review field.
     */
    public function rules(): array
    {
        $householdId = $this->resolveImportHouseholdId();

        return [
            'type' => ['sometimes', 'string', 'in:expense,income'],
            'scope' => ['sometimes', 'string', 'in:personal,shared'],
            'title' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9]{2,10}$/'],
            'account_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('economic_accounts', 'id')->where('user_id', $this->user()?->id)],
            'place' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.total' => ['required_with:items', 'numeric', 'min:0'],
            'taxes' => ['nullable', 'array'],
            'taxes.*.name' => ['required_with:taxes', 'string'],
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
            'participants.*.amount' => ['required_if:participants.*.split_type,fixed', 'nullable', 'numeric', 'min:0'],
            'participants.*.percentage' => ['required_if:participants.*.split_type,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
