<?php

namespace App\Http\Requests;

use App\Support\SecurityConfirmation;
use Illuminate\Foundation\Http\FormRequest;

class DeleteGoogleIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if ($this->routeIs('api.v1.*')) {
            SecurityConfirmation::authorize($this, $user);
        }
        abort_if($user->googleIdentity()->doesntExist(), 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
