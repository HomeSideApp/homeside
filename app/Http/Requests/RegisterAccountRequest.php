<?php

namespace App\Http\Requests;

use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the public web registration payload.
 *
 * The request only validates the payload; the channel policy, the identity
 * block and the "never overwrite an existing account" rule are enforced by the
 * shared RegisterAccount use case, so this endpoint cannot be opened by a
 * request-level toggle. Precognition runs the same rules and never reaches the
 * controller, so validating in real time has no side effect.
 */
class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
            'locale' => ['sometimes', Rule::enum(AppLocale::class)],
        ];
    }
}
