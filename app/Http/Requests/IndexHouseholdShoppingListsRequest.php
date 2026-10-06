<?php

namespace App\Http\Requests;

use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class IndexHouseholdShoppingListsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && app(HouseholdAccess::class)->allows($user, $this->route('household'), 'shopping_lists');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
