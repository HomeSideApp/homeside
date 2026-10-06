<?php

namespace App\Http\Requests;

class DiscoverContactServerRequest extends CreateContactSourceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:1000'],
        ];
    }
}
