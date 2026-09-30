<?php

namespace App\Http\Resources\Categories;

use App\Http\Resources\ShoppingLists\ProductResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
final class CategoryResource extends JsonResource
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
            'color' => $this->color,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
