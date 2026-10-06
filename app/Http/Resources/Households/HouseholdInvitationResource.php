<?php

namespace App\Http\Resources\Households;

use App\Enums\HouseholdAccessProfile;
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
            'access_profile' => $this->access_profile !== null ? $this->access_profile->value : 'member',
            'module_access' => $this->module_access ?? HouseholdAccessProfile::Member->permissions(),
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
