<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;

class ManageGlobalAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AiProvider $provider */
        $provider = $this->route('provider');
        abort_unless($provider->isGlobal(), 404);

        return $this->user()?->can('manage', $provider) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
