<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class PromotePersonalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return ($this->user()?->getPermissionRouteNames()->contains('admin.personal-products.promote') ?? false)
            && $product->is_personal && $product->creator?->shares_personal_products;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:500'],
        ];
    }
}
