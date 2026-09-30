<?php

namespace App\Http\Resources\Households;

use App\Models\HouseholdInviteLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdInviteLink */
final class HouseholdInviteLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'url' => route('invite-links.show', $this->token),
            'expires_at' => $this->expires_at?->toISOString(),
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'revoked_at' => $this->revoked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'usable' => $this->isUsable(),
        ];
    }
}
