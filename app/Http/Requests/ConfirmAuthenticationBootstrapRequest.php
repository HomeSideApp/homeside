<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the TOTP code submitted to the enrolment bootstrap confirm step.
 *
 * The credential of this endpoint is the bootstrap token in the URL, not the
 * session, so the request is intentionally open to guests: there is nothing to
 * authorise beyond the token, which the controller resolves and re-checks. The
 * only field the client may send is the six-digit code, and its shape
 * (`required`, `string`, `size:6`) is part of the contract: the controller
 * hands it to `CompleteAuthenticationBootstrap`, which decides both the code
 * verdict and whether a session may be established.
 */
class ConfirmAuthenticationBootstrapRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
