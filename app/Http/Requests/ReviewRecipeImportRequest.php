<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRecipeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recipe' => ['required', 'array'],
            'recipe.name' => ['required', 'string', 'max:255'],
            'recipe.ingredients' => ['present', 'array'],
            'recipe.steps' => ['present', 'array'],
        ];
    }
}
