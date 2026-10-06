<?php

namespace App\Http\Requests;

class EconomyOverviewRequest extends ReadEconomyRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'months' => ['sometimes', 'integer', 'between:1,24'],
            'currency' => ['sometimes', 'string', 'size:3', 'uppercase'],
        ];
    }
}
