<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TestContactConnectionRequest extends CreateContactSourceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['carddav'])],
            'server_url' => ['required', 'url:http,https', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:1000'],
        ];
    }
}
