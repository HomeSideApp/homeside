<?php

namespace App\Http\Resources\Recipes;

use App\Models\RecipeStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeStep */
final class RecipeStepResource extends JsonResource
{
    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_id' => $this->recipe_id,
            'section_id' => $this->section_id,
            'description' => $this->description,
            'image_url' => $this->image_path && $request->routeIs('api.v1.*')
                ? route('api.v1.recipes.steps.image', [
                    'recipe' => $this->recipe_id,
                    'step' => $this->id,
                    'v' => $this->updated_at?->timestamp ?? time(),
                ])
                : null,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'timers' => RecipeStepTimerResource::collection($this->whenLoaded('timers')),
        ];
    }
}
