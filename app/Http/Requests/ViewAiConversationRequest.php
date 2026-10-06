<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViewAiConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('conversation')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
