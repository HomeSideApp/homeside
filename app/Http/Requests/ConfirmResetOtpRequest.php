<?php

namespace App\Http\Requests;

use App\Concerns\OtpValidationRules;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Class ConfirmResetOtpRequest
 *
 * This form request validates the one-time password (OTP) used to confirm the
 * reset of a two-factor authentication code.
 */
class ConfirmResetOtpRequest extends SignedOtpSetupRequest
{
    use OtpValidationRules;

    protected function passedValidation(): void
    {
        $secret = $this->session()->get('otp_reset_secret_'.$this->setupUser()->id);
        if (! $secret) {
            throw new HttpResponseException(back()->withErrors(['code' => __('app.auth.reset_session_expired')]));
        }
        if (! (new Google2FA)->verifyKey($secret, $this->validated('code'))) {
            throw new HttpResponseException(back()->withErrors(['code' => __('app.auth.invalid_otp')]));
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => $this->otpCodeRules()];
    }
}
