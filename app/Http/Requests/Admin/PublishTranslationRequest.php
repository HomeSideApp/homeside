<?php

namespace App\Http\Requests\Admin;

use App\Concerns\TranslationLocaleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the publish/unpublish gesture from the admin panel. Publishing
 * itself still requires completeness, enforced by the PublishTranslation action.
 */
class PublishTranslationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'translatable_type' => ['required', 'string', Rule::in(['category', 'product', 'store', 'tag'])],
            'translatable_id' => ['required', 'string', 'uuid'],
            // Any well-formed BCP 47 locale (free languages), not just AppLocale.
            'locale' => TranslationLocaleRules::localeRule(),
            'publish' => ['required', 'boolean'],
        ];
    }
}
