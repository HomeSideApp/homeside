<?php

namespace App\Http\Requests;

class SendAiMessageRequest extends ViewAiConversationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:2000']];
    }
}
