<?php

namespace App\Http\Requests;

use App\Models\RecipeCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:/[\\\\\/:*?"<>|]/'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists((new RecipeCollection)->getTable(), 'id')
                    ->where('owner_id', $userId),
            ],
        ];
    }
}
