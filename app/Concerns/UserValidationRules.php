<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for user management requests.
 */
trait UserValidationRules
{
    /**
     * Get the validation rules for creating a new user.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userCreateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', 'exists:roles,slug'],
        ];
    }

    /**
     * @param  string  $userId  The user id.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function userUpdateRules(string $userId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($userId)],
            'role' => ['required', 'exists:roles,slug'],
        ];
    }
}
