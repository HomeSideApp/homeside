<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GoogleOtpSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canAccessApplication()
            && $user->currentAccessToken()->name === 'google-otp-setup'
            && $user->currentAccessToken()->can('otp-setup')
            && ! $user->hasEnabledTwoFactorAuthentication();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
