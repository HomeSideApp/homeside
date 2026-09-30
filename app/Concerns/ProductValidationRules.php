<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Shared validation rules for product requests.
 */
trait ProductValidationRules
{
    /**
     * Get the validation rules for a product, optionally ignoring
     * the unique name constraint for the given product.
     *
     *
     * @param  ?string  $productId  The productId value.
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function productRules(?string $productId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
