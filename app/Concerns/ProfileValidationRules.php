<?php

namespace App\Concerns;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for user profile requests.
 */
trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     *
     * @param  ?string  $userId  The user id.
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?string $userId = null): array
    {
        $user = $userId !== null ? User::query()->find($userId) : null;

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
            'locale' => ['required', Rule::enum(AppLocale::class)],
            'households_enabled' => ['sometimes', 'boolean'],
            'shares_personal_products' => ['sometimes', 'boolean'],
            // The default economy destination may only be a household the user belongs to;
            // a null value means the private account.
            'active_household_id' => [
                'nullable',
                'uuid',
                Rule::exists('households', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'id',
                        $user?->households()->pluck('households.id')->all() ?? [],
                    )
                ),
            ],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     *
     * @param  ?string  $userId  The user id.
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?string $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
