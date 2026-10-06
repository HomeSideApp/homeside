<?php

namespace App\Http\Requests;

use Illuminate\Http\Exceptions\HttpResponseException;

class SignedOtpResetRequest extends SignedOtpSetupRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        if (! $this->setupUser()->hasEnabledTwoFactorAuthentication()) {
            throw new HttpResponseException(back()->withErrors(['error' => __('app.auth.otp_not_configured')]));
        }

        return true;
    }
}
