<?php

namespace App\Http\Requests;

use App\Support\HouseholdAccess;

class CreateEconomicTransactionFormRequest extends ReadEconomyRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $household = $this->economicHousehold();

        return $user !== null && ($household === null || app(HouseholdAccess::class)->allows($user, $household, 'economy', true));
    }
}
