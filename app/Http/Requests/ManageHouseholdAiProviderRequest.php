<?php

namespace App\Http\Requests;

use App\Models\Household;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;

class ManageHouseholdAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Household $household */
        $household = $this->route('household');
        /** @var AiProvider $provider */
        $provider = $this->route('provider');
        abort_unless($provider->household_id === $household->id && $provider->user_id === null, 404);

        return $this->user()?->can('manage', $provider) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
