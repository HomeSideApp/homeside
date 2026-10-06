<?php

namespace App\Http\Requests;

use App\Support\SecurityConfirmation;
use Illuminate\Foundation\Http\FormRequest;

class SecurityConfirmedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        SecurityConfirmation::authorize($this, $user);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
