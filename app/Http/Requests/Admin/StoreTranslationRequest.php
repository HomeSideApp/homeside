<?php

namespace App\Http\Requests\Admin;

use App\Concerns\TranslationLocaleRules;
use App\Enums\TranslationFieldStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the creation of a field translation from the admin panel.
 * The Actions re-validate locale/entity, so this request stays light.
 */
class StoreTranslationRequest extends FormRequest
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
            // Allowlist: only 'name' is translatable for now. Reuse
            // getTranslatableAttributes() when more fields are added.
            'field' => ['required', 'string', Rule::in(['name'])],
            'value' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(TranslationFieldStatus::class)],
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
