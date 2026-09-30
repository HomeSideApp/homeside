<?php

namespace App\Http\Requests;

use App\Concerns\OtpValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class ConfirmResetOtpRequest
 *
 * This form request validates the one-time password (OTP) used to confirm the
 * reset of a two-factor authentication code.
 */
class ConfirmResetOtpRequest extends FormRequest
{
    use OtpValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->otpCodeRules();
    }
}
