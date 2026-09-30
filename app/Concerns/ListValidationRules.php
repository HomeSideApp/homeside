<?php

namespace App\Concerns;

/**
 * Shared validation rules for shopping list requests.
 */
trait ListValidationRules
{
    /**
     * Get the validation rules for a shopping list.
     *
     * @return array<string, mixed>
     */
    protected function listRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
