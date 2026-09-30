<?php

namespace App\Http\Resources\Recipes;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class RecipeCollection extends ResourceCollection
{
    public $collects = RecipeResource::class;

    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection?->map(
            fn (RecipeResource $resource): array => $resource->resolve($request),
        )->all() ?? [];
    }

    /**
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total' => $this->collection?->count() ?? 0,
            ],
        ];
    }
}
