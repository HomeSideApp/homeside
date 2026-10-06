<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contact')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
