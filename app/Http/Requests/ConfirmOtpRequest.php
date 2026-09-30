<?php

namespace App\Http\Requests;

use App\Concerns\OtpValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class ConfirmOtpRequest
 *
 * This form request validates the one-time password (OTP) used to confirm
 * a two-factor authentication setup.
 */
class ConfirmOtpRequest extends FormRequest
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
