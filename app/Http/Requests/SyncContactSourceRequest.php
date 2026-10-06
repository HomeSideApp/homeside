<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncContactSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', $this->route('source')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
