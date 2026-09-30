<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreListItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'exists:products,id'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'icon' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}
