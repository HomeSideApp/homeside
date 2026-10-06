<?php

namespace App\Http\Requests;

class DeleteAiConversationRequest extends ViewAiConversationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('conversation')) ?? false;
    }
}
