<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexStoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        $household = $this->route('household');

        return $this->user()?->can('view', $household) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
