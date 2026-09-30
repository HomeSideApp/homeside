<?php

namespace App\Http\Requests\Admin;

use App\Enums\AiProviderModule;
use App\Http\Requests\ProviderSpecificationRules;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Enums\FallbackPolicy;
use HomeSide\AiAgents\Enums\PrivacyLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGlobalAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', Rule::in(['openai-compatible', 'openai', 'anthropic', 'gemini'])],
            'driver' => ['sometimes', 'string', Rule::in(AiDriver::cases())],
            'base_url' => ['sometimes', 'url', 'max:255'],
            'model' => ['sometimes', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'module' => ['sometimes', Rule::prohibitedIf(fn (): bool => $this->has('modules')), 'string', Rule::in(AiProviderModule::cases())],
            'modules' => ['sometimes', Rule::prohibitedIf(fn (): bool => $this->has('module')), 'array', 'min:1'],
            'modules.*' => ['required', 'string', 'distinct', Rule::in(AiProviderModule::cases())],
            'enabled' => ['sometimes', 'boolean'],
            'configuration' => ['sometimes', 'array'],
            'configuration.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'privacy_level' => ['sometimes', 'string', Rule::in(PrivacyLevel::cases())],
            'fallback_policy' => ['sometimes', 'string', Rule::in(FallbackPolicy::cases())],
            ...ProviderSpecificationRules::rules(),
        ];
    }
}
