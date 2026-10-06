<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && hash_equals((string) $this->route('id'), (string) $user->getKey())
            && hash_equals((string) $this->route('hash'), sha1($user->getEmailForVerification()));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
