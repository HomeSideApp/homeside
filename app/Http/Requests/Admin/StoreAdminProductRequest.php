<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProductValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdminProductRequest extends FormRequest
{
    use ProductValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
