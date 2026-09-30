<?php

namespace App\Http\Requests;

use App\Concerns\ProductValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
