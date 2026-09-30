<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHouseholdInviteLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
