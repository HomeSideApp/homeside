<?php

namespace App\Http\Requests\Admin;

use App\Concerns\TranslationLocaleRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the AI translation generation request from the admin panel and
 * the API. `ids` is optional; when omitted, all entities of the type that
 * are not published for the target locale are generated.
 */
class GenerateTranslationsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'translatable_type' => ['required', 'string', Rule::in(['category', 'product', 'store', 'tag'])],
            // Any well-formed BCP 47 locale (free languages), not just AppLocale.
            'locale' => TranslationLocaleRules::localeRule(),
            'ids' => ['sometimes', 'nullable', 'array'],
            'ids.*' => ['string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationData(): array
    {
        return $this->all();
    }
}
