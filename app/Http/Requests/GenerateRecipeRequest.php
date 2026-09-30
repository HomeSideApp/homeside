<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
        ];
    }
}
