<?php

namespace App\Http\Requests;

use App\Concerns\AuthorizesEconomicRoute;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class ReadEconomyRequest extends FormRequest
{
    use AuthorizesEconomicRoute;

    public function authorize(): bool
    {
        $user = $this->user();
        $household = $this->economicHousehold();

        return $user !== null && ($household === null || app(HouseholdAccess::class)->allows($user, $household, 'economy'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
