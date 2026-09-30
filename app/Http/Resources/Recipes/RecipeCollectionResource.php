<?php

namespace App\Http\Resources\Recipes;

use App\Models\RecipeCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeCollection */
final class RecipeCollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'path' => $this->path,
            'recipes_count' => $this->whenCounted('recipes'),
            'recipes' => $this->whenLoaded('recipes', fn () => $this->recipes->map(fn ($recipe): array => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'collection_id' => $recipe->collection_id,
            ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
