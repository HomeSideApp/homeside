<?php

namespace App\Http\Resources\Households;

use App\Http\Resources\Recipes\RecipeResource;
use App\Models\HouseholdRecipe;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdRecipe */
final class HouseholdRecipeResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'recipe_id' => $this->recipe_id,
            'shared_by' => $this->shared_by,
            'is_favorite' => $this->is_favorite,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'recipe' => $this->whenLoaded('recipe', fn () => new RecipeResource($this->recipe)),
            'shared_by_user' => $this->whenLoaded('sharedBy', fn () => [
                'id' => $this->sharedBy?->id,
                'name' => $this->sharedBy?->name,
            ]),
        ];
    }
}
