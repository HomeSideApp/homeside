<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('contact')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
