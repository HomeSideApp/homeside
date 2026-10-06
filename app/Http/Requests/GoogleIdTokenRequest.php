<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GoogleIdTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['id_token' => ['required', 'string', 'max:8192']];
    }

    public function authorizeAccount(?User $user): User
    {
        abort_unless($user !== null && $user->canAccessApplication(), 403, __('app.errors.account_without_role'));

        return $user;
    }
}
