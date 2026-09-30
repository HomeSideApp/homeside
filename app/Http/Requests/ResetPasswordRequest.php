<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class ResetPasswordRequest
 *
 * This form request validates the password used to reset a user's account password.
 */
class ResetPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }
}
