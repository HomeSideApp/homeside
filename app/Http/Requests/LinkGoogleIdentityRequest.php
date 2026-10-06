<?php

namespace App\Http\Requests;

class LinkGoogleIdentityRequest extends SecurityConfirmedRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id_token' => ['required', 'string', 'max:8192']];
    }
}
