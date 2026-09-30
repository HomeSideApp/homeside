<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactCollectionResource extends JsonResource
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
            'source_id' => $this->contact_source_id,
            'remote_id' => $this->remote_id,
            'name' => $this->name,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'read_only' => $this->read_only,
            'last_synced_at' => $this->last_synced_at,
        ];
    }
}
