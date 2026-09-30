<?php

namespace App\Http\Resources\Assistant;

use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiConversation */
final class AiConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent' => $this->agent,
            'title' => $this->title ?? 'Nueva conversación',
            'runs_count' => $this->whenCounted('runs'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
