<?php

namespace App\Http\Resources\Products;

use App\Http\Resources\Categories\CategoryResource;
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
        // Handle both Model instances and arrays (from SearchProducts action)
        if (is_array($this->resource)) {
            return [
                'id' => $this->resource['id'],
                'name' => $this->resource['name'],
                'slug' => $this->resource['slug'],
                'icon' => $this->resource['icon'],
                'created_at' => null,
                'updated_at' => null,
                'category' => $this->resource['category'] ?? null,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->localized('name'),
            'slug' => $this->slug,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'needs_image' => $this->needs_image,
            'is_approved' => $this->is_approved,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
