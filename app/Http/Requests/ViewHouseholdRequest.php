<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('household')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
