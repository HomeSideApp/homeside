<?php

namespace App\Http\Resources\Households;

use App\Enums\InvitationStatus;
use App\Models\HouseholdInvitation;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdInvitation */
final class HouseholdInvitationResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $status = $this->status === InvitationStatus::Pending && $this->isExpired()
            ? InvitationStatus::Expired
            : $this->status;

        return [
            'id' => $this->id,
            'email' => $this->email,
            'status' => $status->value,
            'status_label' => $status->label(),
            'expires_at' => $this->expires_at->toISOString(),
            'household' => [
                'id' => $this->household?->id,
                'name' => $this->household?->name,
            ],
            'inviter' => [
                'id' => $this->inviter?->id,
                'name' => $this->inviter?->name,
                'email' => $this->inviter?->email,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
