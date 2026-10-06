<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->user()?->currentAccessToken()?->can('otp-verify') === true, 401, __('app.errors.token_temporal_invalid'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'size:6']];
    }
}
