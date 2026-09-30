<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProductValidationRules;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminProductRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');

        return $this->productRules($product instanceof Product ? $product->id : null);
    }
}
