<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for role requests.
 */
trait RoleValidationRules
{
    /**
     * Get the validation rules for a role, optionally ignoring
     * the unique name constraint for the given role.
     *
     *
     * @param  ?string  $roleId  The roleId value.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function roleRules(?string $roleId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $roleId === null
                    ? Rule::unique('roles', 'name')
                    : Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }
}
