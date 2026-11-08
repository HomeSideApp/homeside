<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates the public "I do not have an enrolment link" form.
 *
 * This is a web form, so it validates with Precognition: the browser asks for
 * an early validation response while the visitor types, and that request must
 * never create a grant, send a mail or reveal whether the address exists. The
 * short-circuit lives in the framework's precognitive middleware, before this
 * request reaches the controller, so a precognitive request has no side effect
 * by construction.
 *
 * Only the shape of the address is validated. Whether an account exists is
 * deliberately NOT a validation rule: a validation error for an unknown address
 * would turn the form into an account oracle.
 */
class ResendEnrolmentByEmailRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * The normalised email address the request is about.
     *
     * Normalisation happens once, here, so the lookup and the rate-limit key
     * can never disagree about which address was meant.
     *
     * @return string The trimmed, lower-cased address.
     */
    public function normalisedEmail(): string
    {
        return Str::lower(trim((string) $this->input('email')));
    }
}
