<?php

namespace App\Http\Requests;

use App\Enums\AiProviderModule;
use Illuminate\Validation\Rule;

class DefaultHouseholdAiProviderRequest extends ManageHouseholdAiProviderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]];
    }
}
