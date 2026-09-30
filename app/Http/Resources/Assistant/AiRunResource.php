<?php

namespace App\Http\Resources\Assistant;

use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiRun */
final class AiRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $status = match ($this->status) {
            'ok', 'success' => 'succeeded',
            'error' => 'failed',
            default => $this->status,
        };

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'agent' => $this->agent,
            'status' => $status,
            'message' => $this->user_message,
            'response' => $this->reply,
            'error_code' => $this->error_code,
            'recipe_key' => $this->metadata['recipe_key'] ?? null,
            'result' => $this->metadata['result'] ?? null,
            'poll_after_ms' => in_array($status, ['queued', 'running'], true) ? 1000 : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
