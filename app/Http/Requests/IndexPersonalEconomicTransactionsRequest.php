<?php

namespace App\Http\Requests;

class IndexPersonalEconomicTransactionsRequest extends ReadEconomyRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'in:expense,income'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:occurred_at,amount_minor,created_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
