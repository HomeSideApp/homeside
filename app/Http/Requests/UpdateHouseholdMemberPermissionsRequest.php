<?php

namespace App\Http\Requests;

use App\Enums\HouseholdAccessLevel;
use App\Enums\HouseholdAccessProfile;
use App\Models\Household;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseholdMemberPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');

        return $household instanceof Household && $this->user() !== null && app(HouseholdAccess::class)->manages($this->user(), $household);
    }

    /** @return array<string, mixed> */
    public static function accessRules(): array
    {
        return [
            'access_profile' => ['sometimes', Rule::enum(HouseholdAccessProfile::class)],
            'module_access' => ['sometimes', 'array:shopping_lists,recipes,economy,contacts'],
            'module_access.*' => ['required', Rule::enum(HouseholdAccessLevel::class)],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::accessRules();
    }
}
