<?php

namespace App\Http\Resources\Recipes;

use App\Http\Resources\Households\HouseholdRecipeResource;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recipe */
final class RecipeResource extends JsonResource
{
    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'servings' => $this->servings,
            'yield_text' => $this->yield_text,
            'prep_time_seconds' => $this->prep_time_seconds,
            'cook_time_seconds' => $this->cook_time_seconds,
            'total_time_seconds' => $this->total_time_seconds,
            'difficulty' => $this->difficulty,
            'cuisine' => $this->cuisine,
            'locale' => $this->locale,
            'cooking_method' => $this->cooking_method,
            'recipe_category' => $this->recipe_category,
            'suitable_for_diet' => $this->suitable_for_diet,
            'keywords' => $this->keywords,
            'author' => $this->author,
            'source_url' => $this->source_url,
            'source_name' => $this->source_name,
            'source_type' => $this->source_type,
            'cover_image_url' => ! empty($this->cover_image_path)
                ? route(
                    $request->routeIs('api.v1.*') ? 'api.v1.recipes.image' : 'recipes.image',
                    ['recipe' => $this->id, 'v' => $this->updated_at->timestamp ?? time()],
                )
                : null,
            'notes' => $this->notes,
            'owner_id' => $this->owner_id,
            'created_by' => $this->created_by,
            'derived_from_recipe_id' => $this->derived_from_recipe_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'sections' => RecipeSectionResource::collection($this->whenLoaded('sections')),
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('ingredients')),
            'steps' => RecipeStepResource::collection($this->whenLoaded('steps')),
            'cookware' => RecipeCookwareResource::collection($this->whenLoaded('cookware')),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
            ]),
            'derived_from' => $this->whenLoaded('derivedFrom', fn () => new self($this->derivedFrom)),
            'household_shares' => HouseholdRecipeResource::collection($this->whenLoaded('householdShares')),
        ];
    }
}
