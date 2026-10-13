<?php

namespace App\Http\Resources\Auth;

use App\Data\Auth\RegistrationCapabilitiesData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public capabilities for a client built against the registration API.
 *
 * This is INFORMATION, NOT AUTHORISATION. Every mutating endpoint re-evaluates
 * the registration gate, so a client must never treat a capability read here as
 * permission: an administrator can change a policy between this read and the
 * mutation, and the mutation is the only place the decision counts.
 *
 * @property RegistrationCapabilitiesData $resource
 */
final class RegistrationCapabilitiesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  The incoming request.
     * @return array<string, mixed> The serialised capabilities.
     */
    public function toArray(Request $request): array
    {
        return [
            'channels' => $this->resource->channels,
            'google_available' => $this->resource->googleAvailable,
        ];
    }
}
