<?php

namespace App\Http\Requests;

class UpdateGoogleContactSourceRequest extends ManageGoogleContactSourceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'favorite_label' => ['required', 'string', 'max:80'],
            'enabled' => ['required', 'boolean'],
            'sync_enabled' => ['required', 'boolean'],
        ];
    }
}
