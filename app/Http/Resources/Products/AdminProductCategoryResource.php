<?php

namespace App\Http\Resources\Products;

use App\Models\Category;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Source-name category resource for admin-only surfaces. See
 * AdminProductResource for the rationale.
 *
 * @mixin Category
 */
final class AdminProductCategoryResource extends JsonResource
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
            'color' => $this->color,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
