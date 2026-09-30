<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'household_id' => $this->household_id,
            'enabled' => $this->enabled,
            'sync_enabled' => $this->sync_enabled,
            'authentication_type' => $this->authentication_type,
            'provider_configuration' => $this->provider_configuration,
            'last_synced_at' => $this->last_synced_at,
            'last_sync_status' => $this->last_sync_status,
            'last_sync_error' => $this->last_sync_error,
            'collections' => $this->whenLoaded(
                'collections',
                fn (): array => ContactCollectionResource::collection($this->collections)->resolve($request),
            ),
        ];
    }
}
