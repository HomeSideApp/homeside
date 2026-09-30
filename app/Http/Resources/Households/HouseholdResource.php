<?php

namespace App\Http\Resources\Households;

use App\Enums\HouseholdModule;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdModule as HouseholdModuleModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Household */
final class HouseholdResource extends JsonResource
{
    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();
        $isMemberAdmin = false;

        if ($currentUser && $this->relationLoaded('members')) {
            $membership = $this->members->firstWhere('user_id', $currentUser->id);
            $isMemberAdmin = $membership?->role === HouseholdRole::Admin;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'image_url' => $this->image_url
                ? route($request->routeIs('api.v1.*') ? 'api.v1.households.image' : 'households.image', $this->id)
                : null,
            'invite_code' => $this->when($isMemberAdmin, $this->invite_code),
            'settings' => $this->settings,
            'created_by' => $this->created_by,
            // Lets the client build the transaction destination selector without issuing one
            // request per household. Omitted when the modules relation was not loaded.
            'economy_module_enabled' => $this->whenLoaded(
                'modules',
                fn (): bool => $this->modules->contains(
                    fn (HouseholdModuleModel $module): bool => $module->module === HouseholdModule::Economy->value
                        && $module->enabled
                ),
            ),
            'members_count' => $this->whenCounted('members'),
            'members' => HouseholdMemberResource::collection($this->whenLoaded('members')),
            'pending_invitations' => HouseholdInvitationResource::collection($this->whenLoaded('pendingInvitations')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
