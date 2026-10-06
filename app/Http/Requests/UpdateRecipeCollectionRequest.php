<?php

namespace App\Http\Requests;

use App\Models\RecipeCollection;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRecipeCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RecipeCollection|null $collection */
        $collection = $this->route('recipeCollection');
        $allowed = $collection?->owner_id === $this->user()?->id && $this->user() !== null;
        if ($this->routeIs('api.v1.*')) {
            abort_unless($allowed, 404);
        }

        return $allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\\\\\/:*?"<>|]/'],
        ];
    }
}
