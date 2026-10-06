<?php

namespace App\Http\Requests;

use App\Models\EconomicAccount;
use Illuminate\Foundation\Http\FormRequest;

class IndexEconomicAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EconomicAccount::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
