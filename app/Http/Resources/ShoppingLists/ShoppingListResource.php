<?php

namespace App\Http\Resources\ShoppingLists;

use App\Models\ShoppingList;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ShoppingList */
final class ShoppingListResource extends JsonResource
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
            'created_by' => $this->created_by,
            'household_id' => $this->household_id,
            'scope' => $this->household_id === null ? 'personal' : 'household',
            'household' => $this->whenLoaded('household', fn (): ?array => $this->household === null
                ? null
                : [
                    'id' => $this->household->id,
                    'name' => $this->household->name,
                ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'items' => ListItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
