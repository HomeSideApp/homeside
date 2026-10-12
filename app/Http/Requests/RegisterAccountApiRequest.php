<?php

namespace App\Http\Requests;

use App\Enums\AppLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAccountApiRequest extends FormRequest
{
    /**
     * The request only validates the payload.
     *
     * Channel policy is enforced by the controller through
     * App\Contracts\RegistrationGate, never here, so this endpoint cannot be
     * opened by a request-level toggle and the decision stays auditable in one
     * place.
     */
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
