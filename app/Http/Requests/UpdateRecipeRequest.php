<?php

namespace App\Http\Requests;

use App\Concerns\RecipeValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecipeRequest extends FormRequest
{
    use RecipeValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('recipe')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->recipeRules();
    }
}
