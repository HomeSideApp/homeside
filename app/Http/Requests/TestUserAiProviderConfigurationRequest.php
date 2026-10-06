<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Enums\AiDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestUserAiProviderConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array{type: string, base_url: string, model: string, api_key: string} */
    public function configuration(): array
    {
        return [
            'type' => (string) $this->validated('type'),
            'base_url' => (string) $this->validated('base_url'),
            'model' => (string) $this->validated('model'),
            'api_key' => (string) $this->validated('api_key'),
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(AiDriver::cases())],
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
