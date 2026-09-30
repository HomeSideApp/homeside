<?php

namespace App\Http\Requests\Admin;

use App\Concerns\TranslationLocaleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPublishTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['selected', 'filtered'])],
            'translatable_type' => ['required', Rule::in(['category', 'product', 'store', 'tag'])],
            'locale' => TranslationLocaleRules::localeRule(),
            'ids' => ['required_if:mode,selected', 'prohibited_unless:mode,selected', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'uuid', 'distinct'],
            'status' => ['nullable', Rule::in(['all', 'untranslated', 'incomplete', 'complete', 'published'])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
