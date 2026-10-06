<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GoogleLoginCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @phpstan-assert-if-true User $user */
    public function canLogin(?User $user): bool
    {
        return $user !== null && $user->canAccessApplication();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
