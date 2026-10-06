<?php

namespace App\Http\Requests;

use App\Enums\AiProviderModule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalModulePromptApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(in_array($this->route('module'), array_column(AiProviderModule::cases(), 'value'), true), 404);

        return $this->user()?->getPermissionRouteNames()->contains('admin.ai-settings.module-prompt') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['extra_prompt' => ['nullable', 'string', 'max:20000']];
    }
}
