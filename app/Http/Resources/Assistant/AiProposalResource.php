<?php

namespace App\Http\Resources\Assistant;

use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiActionProposal */
final class AiProposalResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     conversation_id: string|null,
     *     household_id: string|null,
     *     type: 'add_shopping_items'|'create_recipe',
     *     status: 'pending'|'accepted'|'rejected'|'expired',
     *     payload: array{list_id: string, items: list<array{product_id?: string|null, custom_name?: string|null, quantity?: int|float, unit?: string|null, notes?: string|null}>}|array{recipe: array<string, mixed>},
     *     reason: string|null,
     *     expires_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'household_id' => $this->household_id,
            'type' => $this->type,
            'status' => $this->expires_at?->isPast() && $this->status === 'pending' ? 'expired' : $this->status,
            'payload' => $this->payload,
            'reason' => $this->reason,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
