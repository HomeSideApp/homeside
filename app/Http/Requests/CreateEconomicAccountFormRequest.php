<?php

namespace App\Http\Requests;

use App\Models\EconomicAccount;
use Illuminate\Foundation\Http\FormRequest;

class CreateEconomicAccountFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EconomicAccount::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
