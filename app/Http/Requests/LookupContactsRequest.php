<?php

namespace App\Http\Requests;

class LookupContactsRequest extends IndexContactsRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'scope' => ['nullable', 'uuid'],
            'by' => ['nullable', 'in:name,email'],
        ];
    }
}
