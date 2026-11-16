<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the bootstrap-authenticated two-factor setup exchange.
 *
 * The endpoint is public: the single-use enrolment token in the body IS the
 * credential, and the controller keeps deciding what it may redeem. The request
 * only states the shape of the payload, so a missing or non-string token fails
 * validation before any repository lookup happens.
 */
class SetupTwoFactorFromBootstrapApiRequest extends FormRequest
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
        ];
    }
}
