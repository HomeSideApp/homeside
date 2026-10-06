<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        abort_if($user->hasEnabledTwoFactorAuthentication(), 409, __('app.errors.two_factor_already_enabled'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
