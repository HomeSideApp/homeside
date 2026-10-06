<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class ViewPersonalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $allowed = $this->user()?->getPermissionRouteNames()->contains('admin.personal-products.show') ?? false;
        if ($allowed) {
            /** @var Product $product */
            $product = $this->route('product');
            abort_unless($product->is_personal && $product->creator?->shares_personal_products, 404);
        }

        return $allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
