<?php

namespace App\Http\Requests;

class TestGlobalAiProviderConfigurationRequest extends TestUserAiProviderConfigurationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.ai-providers') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'base_url' => ['required', 'url'],
            'model' => ['required', 'string'],
            'api_key' => ['required', 'string'],
            'module' => ['sometimes', 'string'],
        ];
    }
}
