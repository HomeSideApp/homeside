<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for category requests.
 */
trait CategoryValidationRules
{
    /**
     * Get the validation rules for a category, optionally ignoring
     * the unique name constraint for the given category.
     *
     *
     * @param  ?string  $categoryId  The categoryId value.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function categoryRules(?string $categoryId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $categoryId === null
                    ? Rule::unique('categories', 'name')
                    : Rule::unique('categories', 'name')->ignore($categoryId),
            ],
            'color' => ['required', 'string', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
