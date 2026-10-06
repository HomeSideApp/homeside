<?php

namespace App\Http\Requests;

use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class ShareHouseholdRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $household = $this->route('household');

        return $user !== null
            && app(HouseholdAccess::class)->allows($user, $household, 'recipes', true)
            && $user->can('share', $this->route('recipe'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
