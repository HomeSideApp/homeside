<?php

namespace App\Http\Requests;

use App\Concerns\OtpValidationRules;

/**
 * Class ConfirmResetOtpRequest
 *
 * This form request validates the one-time password (OTP) used to confirm the
 * reset of a two-factor authentication code.
 *
 * Authorisation and token resolution live in the parent
 * (`SignedOtpResetRequest`), so the same single-use `AccountRecovery` grant
 * guards both the show and the confirm step. The code shape is validated here;
 * the code itself is verified inside `CompleteAuthenticationBootstrap`, which
 * checks it BEFORE consuming the grant so a mistyped code can be retried.
 */
class ConfirmResetOtpRequest extends SignedOtpResetRequest
{
    use OtpValidationRules;

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
