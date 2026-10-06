<?php

namespace App\Http\Requests;

use App\Concerns\OtpValidationRules;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Class ConfirmOtpRequest
 *
 * This form request validates the one-time password (OTP) used to confirm
 * a two-factor authentication setup.
 */
class ConfirmOtpRequest extends SignedOtpSetupRequest
{
    use OtpValidationRules;

    protected function passedValidation(): void
    {
        $user = $this->setupUser();
        $secret = $this->session()->get('otp_secret_'.$user->id);
        if (! $secret) {
            throw new HttpResponseException(back()->withErrors(['code' => __('app.errors.session_expired')]));
        }
        if (! (new Google2FA)->verifyKey($secret, $this->validated('code'))) {
            throw new HttpResponseException(back()->withErrors(['code' => __('app.errors.otp_code_invalid_verify')]));
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
