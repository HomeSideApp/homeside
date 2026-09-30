<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromotePersonalProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['nullable', 'uuid', 'exists:categories,id'],
            'icon' => ['nullable', 'string', 'max:500'],
        ];
    }
}
