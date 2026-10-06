<?php

namespace App\Http\Requests;

use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class IndexHouseholdRecipesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(HouseholdAccess::class)->allows($user, $this->route('household'), 'recipes');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if (! $this->routeIs('api.v1.*')) {
            return [];
        }

        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'tag' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
