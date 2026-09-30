<?php

namespace App\Http\Requests;

final class UpdateEconomicTransactionApiRequest extends UpdateEconomicTransactionRequest
{
    /**
     * Define the mobile API contract using integer minor currency units.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset(
            $rules['amount'],
            $rules['items.*.unit_amount'],
            $rules['items.*.subtotal'],
            $rules['items.*.tax_amount'],
            $rules['items.*.total'],
            $rules['taxes.*.taxable_base'],
            $rules['taxes.*.amount'],
            $rules['participants.*.amount'],
        );

        return [
            ...$rules,
            'amount_minor' => ['sometimes', 'integer', 'min:1'],
            'items.*.unit_amount_minor' => ['required_with:items', 'integer', 'min:0'],
            'items.*.subtotal_minor' => ['required_with:items', 'integer', 'min:0'],
            'items.*.tax_amount_minor' => ['nullable', 'integer', 'min:0'],
            'items.*.total_minor' => ['required_with:items', 'integer', 'min:0'],
            'taxes.*.taxable_base_minor' => ['required_with:taxes', 'integer', 'min:0'],
            'taxes.*.amount_minor' => ['required_with:taxes', 'integer', 'min:0'],
            'participants.*.amount_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
