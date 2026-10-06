<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TemporaryOtpTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->bearerToken() !== null
            && $this->user()?->currentAccessToken()?->name === 'temp-otp', 401, __('app.errors.token_temporal_invalid'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
