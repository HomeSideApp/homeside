<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesShoppingListRoute;
use Illuminate\Foundation\Http\FormRequest;

class UpdateListItemRequest extends FormRequest
{
    use AuthorizesShoppingListRoute;

    public function authorize(): bool
    {
        $this->listItem();

        return $this->user()?->can('update', $this->shoppingList()) ?? false;
    }

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
            // A phone photo is 3-10 MB, and `UpdateListItem` shrinks it to
            // 1024 px and converts it to WebP before storing it, so this cap only
            // has to stop absurd files: 2 MB rejected almost every real photo.
            // The dimensions rule below it prevents decompression bombs.
            'image_url' => [
                'nullable',
                'file',
                'image',
                'max:12288',
                'dimensions:max_width=10000,max_height=10000',
            ],
            'icon' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}
