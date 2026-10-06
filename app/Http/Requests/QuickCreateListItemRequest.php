<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesShoppingListRoute;
use Illuminate\Foundation\Http\FormRequest;

final class QuickCreateListItemRequest extends FormRequest
{
    use AuthorizesShoppingListRoute;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->shoppingList()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product' => ['required', 'array'],
            'product.name' => ['required', 'string', 'max:255'],
            'product.category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'product.icon' => ['nullable', 'string', 'max:255'],
            'item' => ['required', 'array'],
            'item.quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'item.unit' => ['nullable', 'string', 'max:50'],
            'item.notes' => ['nullable', 'string', 'max:255'],
            'item.store_id' => ['nullable', 'uuid', 'exists:stores,id'],
        ];
    }
}
