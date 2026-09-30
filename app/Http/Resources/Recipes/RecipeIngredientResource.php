<?php

namespace App\Http\Resources\Recipes;

use App\Http\Resources\Products\ProductResource;
use App\Models\RecipeIngredient;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeIngredient */
final class RecipeIngredientResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'recipe_id' => $this->recipe_id,
            'section_id' => $this->section_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'quantity' => $this->quantity ? (float) $this->quantity : null,
            'quantity_text' => $this->quantity_text,
            'unit' => $this->unit,
            'preparation' => $this->preparation,
            'notes' => $this->notes,
            'optional' => $this->optional,
            'original_text' => $this->original_text,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
