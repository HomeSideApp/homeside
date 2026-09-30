<?php

namespace App\Http\Requests\Admin;

use App\Enums\AiProviderModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModulePromptRequest extends FormRequest
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
            'module' => ['required', 'string', Rule::in(AiProviderModule::cases())],
            'extra_prompt' => ['required', 'string', 'max:10000'],
        ];
    }
}
