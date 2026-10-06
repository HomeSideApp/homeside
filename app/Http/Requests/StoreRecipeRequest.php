<?php

namespace App\Http\Requests;

use App\Concerns\RecipeValidationRules;
use App\Models\Recipe;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    use RecipeValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Recipe::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->recipeRules();
    }
}
