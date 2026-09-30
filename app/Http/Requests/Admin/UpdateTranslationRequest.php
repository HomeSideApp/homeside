<?php

namespace App\Http\Requests\Admin;

use App\Enums\TranslationFieldStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates updating an existing field translation from the admin panel.
 */
class UpdateTranslationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(TranslationFieldStatus::class)],
        ];
    }
}
