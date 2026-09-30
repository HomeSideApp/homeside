<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_checked' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'image_url' => ['nullable', 'file', 'image', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}
