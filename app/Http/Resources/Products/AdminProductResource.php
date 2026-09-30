<?php

namespace App\Http\Resources\Products;

use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for admin-only surfaces (e.g. the admin product image queue).
 * Unlike the user-facing ProductResource, it always shows the source column
 * value of `name` — admin screens must never display localized values.
 *
 * @mixin Product
 */
final class AdminProductResource extends JsonResource
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
            'slug' => $this->slug,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'image_url' => $this->image_url,
            'needs_image' => $this->needs_image,
            'is_approved' => $this->is_approved,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => new AdminProductCategoryResource($this->whenLoaded('category')),
        ];
    }
}
