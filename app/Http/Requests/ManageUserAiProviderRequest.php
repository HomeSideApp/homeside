<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;

class ManageUserAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        /** @var AiProvider $provider */
        $provider = $this->route('provider');
        abort_unless($provider->isUser() && $provider->user_id === $user->id, 404);

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
