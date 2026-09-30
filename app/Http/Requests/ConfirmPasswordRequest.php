<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class ConfirmPasswordRequest
 *
 * This form request validates the current password used to confirm a user's
 * identity before performing a sensitive action.
 */
class ConfirmPasswordRequest extends FormRequest
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
            'password' => $this->currentPasswordRules(),
        ];
    }
}
