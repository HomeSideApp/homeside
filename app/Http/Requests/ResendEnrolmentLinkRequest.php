<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorises and validates the "resend my expired enrolment link" action.
 *
 * The credential travels in the URL path, so the form carries no fields and
 * there is nothing to validate. The request exists anyway so the endpoint has a
 * single, named place where the shape of the input is stated (none) and so a
 * future field cannot be added without deciding how it is validated.
 *
 * Precognition is harmless here: a precognitive request never reaches the
 * controller, so it can neither issue a grant nor send a mail.
 */
class ResendEnrolmentLinkRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
