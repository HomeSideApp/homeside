<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\AiProviderModule;
use App\Http\Requests\ProviderSpecificationRules;
use App\Models\User;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Enums\FallbackPolicy;
use HomeSide\AiAgents\Enums\PrivacyLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of an AI provider owned by the authenticated user.
 */
final class StoreUserAiProviderRequest extends FormRequest
{
    /**
     * Determine whether an authenticated user may create their own provider.
     *
     * @return bool True when the request has an authenticated user.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the rules for a personal AI provider configuration.
     *
     * @return array<string, mixed> The validation rules keyed by input field.
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
            'configuration.timeout' => ['nullable', 'integer', 'min:10', 'max:300'],
            'configuration.max_tokens' => ['nullable', 'integer', 'min:256', 'max:128000'],
            'configuration.temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'configuration.model_capabilities' => ['nullable', 'array'],
            'configuration.model_capabilities.reasoning' => ['nullable', 'boolean'],
            'configuration.model_capabilities.reasoning_effort' => ['nullable', 'string', 'max:20'],
            'configuration.model_capabilities.tool_call_format' => ['nullable', 'string', 'in:native,xml'],
            'configuration.model_capabilities.context_tokens' => ['nullable', 'integer', 'min:1024', 'max:10000000'],
            'privacy_level' => ['nullable', 'string', Rule::in(PrivacyLevel::cases())],
            'fallback_policy' => ['nullable', 'string', Rule::in(FallbackPolicy::cases())],
            ...ProviderSpecificationRules::rules(),
        ];
    }
}
