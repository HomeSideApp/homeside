<?php

namespace App\Http\Requests;

use App\Models\ContactSource;
use Illuminate\Foundation\Http\FormRequest;

class CreateContactSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContactSource::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
