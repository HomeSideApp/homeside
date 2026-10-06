<?php

namespace App\Http\Requests;

use App\Enums\AppLocale;
use App\Enums\TranslationFieldStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->getPermissionRouteNames()->contains('admin.translations') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', Rule::in(['category', 'product', 'store', 'tag'])],
            'locale' => ['sometimes', 'string', Rule::in(AppLocale::values())],
            'field' => ['sometimes', 'string', Rule::in(['name'])],
            'status' => ['sometimes', 'string', Rule::in(TranslationFieldStatus::values())],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
