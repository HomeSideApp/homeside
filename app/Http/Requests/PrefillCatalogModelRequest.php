<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrefillCatalogModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider_slug' => ['required', 'string', 'max:100'],
            'model_id' => ['required', 'string', 'max:200'],
        ];
    }
}
