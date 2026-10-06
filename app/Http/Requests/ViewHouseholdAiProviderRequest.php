<?php

namespace App\Http\Requests;

use App\Models\Household;
use HomeSide\AiAgents\Models\AiProvider;

class ViewHouseholdAiProviderRequest extends ManageHouseholdAiProviderRequest
{
    public function authorize(): bool
    {
        /** @var Household $household */
        $household = $this->route('household');
        /** @var AiProvider $provider */
        $provider = $this->route('provider');
        abort_unless($provider->household_id === $household->id && $provider->user_id === null, 404);

        return $this->user()?->can('view', $provider) ?? false;
    }
}
