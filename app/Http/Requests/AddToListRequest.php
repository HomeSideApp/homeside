<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddToListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_servings' => ['required', 'integer', 'min:1'],
        ];
    }
}
