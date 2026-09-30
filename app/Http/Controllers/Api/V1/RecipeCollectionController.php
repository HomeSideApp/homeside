<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeCollectionRequest;
use App\Http\Requests\UpdateRecipeCollectionRequest;
use App\Http\Resources\Recipes\RecipeCollectionResource;
use App\Models\RecipeCollection;
use App\Services\Recipes\ManageRecipeCollections;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Manages the authenticated user's recipe collections through the API.
 */
final class RecipeCollectionController extends Controller
{
    /**
     * List personal recipe collections and their recipes.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $collections = RecipeCollection::query()
            ->where('owner_id', $this->authenticatedUser($request)->id)
            ->with(['recipes' => fn ($query) => $query
                ->select('id', 'name', 'collection_id')
                ->orderBy('name')])
            ->withCount('recipes')
            ->orderBy('path')
            ->get();

        return RecipeCollectionResource::collection($collections);
    }

    /**
     * Create a personal recipe collection.
     */
    public function store(
        StoreRecipeCollectionRequest $request,
        ManageRecipeCollections $collections,
    ): JsonResponse {
        $collection = $collections->create(
            $this->authenticatedUser($request),
            $request->validated('name'),
            $request->validated('parent_id'),
        );

        return RecipeCollectionResource::make($collection)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Rename an owned recipe collection.
     */
    public function update(
        UpdateRecipeCollectionRequest $request,
        RecipeCollection $recipeCollection,
        ManageRecipeCollections $collections,
    ): RecipeCollectionResource {
        $this->ensureOwner($request, $recipeCollection);

        return new RecipeCollectionResource(
            $collections->rename($recipeCollection, $request->validated('name')),
        );
    }

    /**
     * Delete an owned recipe collection.
     */
    public function destroy(
        Request $request,
        RecipeCollection $recipeCollection,
        ManageRecipeCollections $collections,
    ): Response {
        $this->ensureOwner($request, $recipeCollection);
        $collections->delete($recipeCollection);

        return response()->noContent();
    }

    /**
     * Ensure the collection belongs to the authenticated user.
     */
    private function ensureOwner(Request $request, RecipeCollection $collection): void
    {
        abort_unless($collection->owner_id === $this->authenticatedUser($request)->id, 404);
    }
}
