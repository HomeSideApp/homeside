<?php

namespace App\Http\Resources\Recipes;

use App\Models\RecipeCookware;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeCookware */
final class RecipeCookwareResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'quantity_text' => $this->quantity_text,
            'unit' => $this->unit,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
