<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForkRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fork', $this->route('recipe')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
