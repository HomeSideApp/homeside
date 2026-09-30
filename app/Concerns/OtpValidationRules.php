<?php

namespace App\Concerns;

/**
 * Shared validation rules for OTP codes.
 */
trait OtpValidationRules
{
    /**
     * Get the validation rules for a six-digit OTP code.
     *
     * @return list<string>
     */
    protected function otpCodeRules(): array
    {
        return ['required', 'string', 'size:6'];
    }
}
