<?php

namespace App\Http\Resources\ShoppingLists;

use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
final class ProductResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localized('name'),
            'slug' => $this->slug,
            'icon' => $this->icon,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
