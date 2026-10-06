<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncContactCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sync', $this->route('collection')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
