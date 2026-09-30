<?php

namespace App\Http\Resources\Recipes;

use App\Models\RecipeStepTimer;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RecipeStepTimer */
final class RecipeStepTimerResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'recipe_step_id' => $this->recipe_step_id,
            'name' => $this->name,
            'duration_seconds' => $this->duration_seconds,
            'order' => $this->order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
