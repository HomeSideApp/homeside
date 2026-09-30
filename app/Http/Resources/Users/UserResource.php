<?php

namespace App\Http\Resources\Users;

use App\Models\User;
use App\Support\ApiOperationPermissions;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            'approval_status' => $this->approval_status,
            'google_connected' => $this->googleIdentity()->exists(),
            'active_household_id' => $this->active_household_id,
            'households_enabled' => $this->households_enabled,
            'roles' => $this->getRoleNames(),
            'permissions' => ApiOperationPermissions::for($this->resource),
        ];
    }
}
