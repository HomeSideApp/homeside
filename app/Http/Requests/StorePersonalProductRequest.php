<?php

namespace App\Http\Requests;

use App\Concerns\ProductValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonalProductRequest extends FormRequest
{
    use ProductValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
