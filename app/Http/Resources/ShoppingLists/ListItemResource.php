<?php

namespace App\Http\Resources\ShoppingLists;

use App\Models\Household;
use App\Models\ListItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ListItem */
final class ListItemResource extends JsonResource
{
    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $household = $request->route('household');
        $householdId = $household instanceof Household ? $household->getRouteKey() : $household;

        return [
            'id' => $this->id,
            'list_id' => $this->list_id,
            'product_id' => $this->product_id,
            'custom_name' => $this->custom_name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'is_checked' => $this->is_checked,
            'notes' => $this->notes,
            'icon' => $this->icon,
            'image_url' => $this->image_url && is_string($householdId)
                ? route('api.v1.households.lists.items.image', [$householdId, $this->list_id, $this->id])
                : null,
            'store_id' => $this->store_id,
            'category_id' => $this->category_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'store' => new StoreResource($this->whenLoaded('store')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'added_by_user' => UserResource::make($this->whenLoaded('addedByUser')),
        ];
    }
}
