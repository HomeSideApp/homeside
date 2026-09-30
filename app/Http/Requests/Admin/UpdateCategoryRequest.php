<?php

namespace App\Http\Requests\Admin;

use App\Concerns\CategoryValidationRules;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    use CategoryValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return $this->categoryRules($category instanceof Category ? $category->id : null);
    }
}
