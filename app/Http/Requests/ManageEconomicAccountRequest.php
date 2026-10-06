<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageEconomicAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('account')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
