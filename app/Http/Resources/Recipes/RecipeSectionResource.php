<?php

namespace App\Http\Resources\Recipes;

use App\Models\RecipeSection;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeSection */
final class RecipeSectionResource extends JsonResource
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
            'name' => $this->name,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
