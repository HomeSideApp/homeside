<?php

namespace App\Http\Requests;

class EconomicTotalsRequest extends ReadEconomyRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['period' => ['sometimes', 'date_format:Y-m']];
    }
}
