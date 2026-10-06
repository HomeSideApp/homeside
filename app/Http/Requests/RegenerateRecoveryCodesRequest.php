<?php

namespace App\Http\Requests;

class RegenerateRecoveryCodesRequest extends SecurityConfirmedRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        abort_unless(($this->user()?->hasEnabledTwoFactorAuthentication() ?? false), 409, __('app.errors.two_factor_not_enabled'));

        return true;
    }
}
