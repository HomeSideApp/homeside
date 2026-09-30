<?php

namespace App\Http\Requests;

use App\Enums\AiProviderModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleAiConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('household')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', Rule::in(AiProviderModule::cases())],
            'agent_name' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'additional_instructions' => ['nullable', 'string', 'max:20000'],
            'description' => ['nullable', 'string'],
            'ai_provider_id' => ['nullable', 'uuid', 'exists:ai_providers,id'],
            'model' => ['nullable', 'string', 'max:255'],
            'parameters' => ['nullable', 'array'],
            'parameters.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'parameters.max_tokens' => ['nullable', 'integer', 'min:1', 'max:128000'],
            'parameters.max_steps' => ['nullable', 'integer', 'min:1', 'max:20'],
            'parameters.timeout' => ['nullable', 'integer', 'min:10', 'max:300'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
