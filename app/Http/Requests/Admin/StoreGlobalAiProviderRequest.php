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

class StoreGlobalAiProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(['openai-compatible', 'openai', 'anthropic', 'gemini'])],
            'driver' => ['nullable', 'string', Rule::in(AiDriver::cases())],
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'module' => ['required_without:modules', Rule::prohibitedIf(fn (): bool => $this->has('modules')), 'string', Rule::in(AiProviderModule::cases())],
            'modules' => ['required_without:module', Rule::prohibitedIf(fn (): bool => $this->has('module')), 'array', 'min:1'],
            'modules.*' => ['required', 'string', 'distinct', Rule::in(AiProviderModule::cases())],
            'enabled' => ['sometimes', 'boolean'],
            'configuration' => ['nullable', 'array'],
            'configuration.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'privacy_level' => ['nullable', 'string', Rule::in(PrivacyLevel::cases())],
            'fallback_policy' => ['nullable', 'string', Rule::in(FallbackPolicy::cases())],
            ...ProviderSpecificationRules::rules(),
        ];
    }
}
