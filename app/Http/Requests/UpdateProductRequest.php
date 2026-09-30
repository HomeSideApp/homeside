<?php

namespace App\Http\Requests;

use App\Concerns\ProductValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class UpdateProductRequest
 *
 * This form request validates the data required to update a product.
 */
class UpdateProductRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
