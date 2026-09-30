<?php

namespace App\Http\Resources\Products;

use App\Http\Resources\Categories\CategoryResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
final class PersonalProductResource extends JsonResource
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
            'is_personal' => $this->is_personal,
            'created_by' => $this->created_by,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
