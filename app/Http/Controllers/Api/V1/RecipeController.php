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
use App\Http\Requests\DeleteRecipeRequest;
use App\Http\Requests\IndexRecipesRequest;
use App\Http\Requests\StoreRecipeApiRequest;
use App\Http\Requests\UpdateRecipeApiRequest;
use App\Http\Requests\ViewRecipeRequest;
use App\Http\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
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
    public function index(ListRecipes $action, IndexRecipesRequest $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $filters = $request->validated();

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
    public function show(Recipe $recipe, GetRecipe $action, ViewRecipeRequest $request): RecipeResource
    {

        $result = $action->execute($recipe, $this->authenticatedUser($request));

        return new RecipeResource($result['recipe']);
    }

    /**
     * Update the given recipe.
     */
    public function update(UpdateRecipeApiRequest $request, Recipe $recipe, UpdateRecipe $action): JsonResponse
    {

        $data = RecipeData::fromArray(array_merge($request->validated(), ['id' => $recipe->id]));
        $recipe = $action->execute($recipe, $data);

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Delete the given recipe.
     */
    public function destroy(Recipe $recipe, DeleteRecipe $action, DeleteRecipeRequest $request): Response
    {

        $action->execute($recipe);

        return response()->noContent();
    }
}
