<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\GetRecipe;
use App\Actions\Recipes\ListRecipes;
use App\Actions\Recipes\UpdateRecipe;
use App\Data\Recipes\CreateRecipeData;
use App\Data\Recipes\RecipeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeApiRequest;
use App\Http\Requests\UpdateRecipeApiRequest;
use App\Http\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Class RecipeController
 *
 * This controller handles the API endpoints for managing recipes, including
 * listing, creating, viewing, updating and deleting recipes.
 */
final class RecipeController extends Controller
{
    /**
     * List recipes visible to the authenticated user.
     */
    public function index(ListRecipes $action, Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'tag' => ['sometimes', 'string', 'max:100'],
            'collection' => ['sometimes', 'uuid'],
            'difficulty' => ['sometimes', 'string', 'max:50'],
            'cuisine' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);

        $recipes = $action->execute($user, $filters);

        return RecipeResource::collection($recipes);
    }

    /**
     * Store a newly created recipe.
     */
    public function store(StoreRecipeApiRequest $request, CreateRecipe $action): JsonResponse
    {
        $data = CreateRecipeData::fromArray($request->validated());
        $user = $this->authenticatedUser($request);

        $recipe = $action->execute($data, $user);

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Return the given recipe.
     */
    public function show(Recipe $recipe, GetRecipe $action, Request $request): RecipeResource
    {
        abort_unless($this->authenticatedUser($request)->can('view', $recipe), 403);

        $result = $action->execute($recipe);

        return new RecipeResource($result['recipe']);
    }

    /**
     * Update the given recipe.
     */
    public function update(UpdateRecipeApiRequest $request, Recipe $recipe, UpdateRecipe $action): JsonResponse
    {
        abort_unless($this->authenticatedUser($request)->can('update', $recipe), 403);

        $data = RecipeData::fromArray(array_merge($request->validated(), ['id' => $recipe->id]));
        $recipe = $action->execute($recipe, $data);

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Delete the given recipe.
     */
    public function destroy(Recipe $recipe, DeleteRecipe $action, Request $request): Response
    {
        abort_unless($this->authenticatedUser($request)->can('delete', $recipe), 403);

        $action->execute($recipe);

        return response()->noContent();
    }
}
