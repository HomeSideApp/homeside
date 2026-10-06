<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;

class IndexAiConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AiConversation::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
