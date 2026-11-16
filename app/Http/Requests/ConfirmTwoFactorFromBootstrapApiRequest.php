<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the bootstrap-authenticated two-factor confirmation.
 *
 * Same public contract as the setup exchange: the enrolment token is the
 * credential and the challenge id names the server-side secret, so both are
 * only required strings here. Whether the challenge exists, belongs to this
 * grant or has expired is decided by the controller, which must answer with its
 * own stable error codes rather than a generic validation error.
 */
class ConfirmTwoFactorFromBootstrapApiRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enrolment_token' => ['required', 'string'],
            'challenge_id' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
