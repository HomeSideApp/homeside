<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\ListHouseholdRecipes;
use App\Actions\Recipes\ShareRecipe;
use App\Actions\Recipes\UnshareRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexHouseholdRecipesRequest;
use App\Http\Requests\ShareHouseholdRecipeRequest;
use App\Http\Requests\UnshareHouseholdRecipeRequest;
use App\Http\Resources\Households\HouseholdRecipeResource;
use App\Models\Household;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Class HouseholdRecipeController
 *
 * This controller handles the API endpoints for listing, sharing and unsharing recipes
 * with a household.
 */
final class HouseholdRecipeController extends Controller
{
    /**
     * List the recipes shared with the given household.
     */
    public function index(Household $household, ListHouseholdRecipes $action, IndexHouseholdRecipesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $recipes = $action->execute($household, $filters);

        return HouseholdRecipeResource::collection($recipes);
    }

    /**
     * Share the given recipe with the household.
     */
    public function share(Household $household, Recipe $recipe, ShareRecipe $action, ShareHouseholdRecipeRequest $request): JsonResponse
    {
        $householdRecipe = $action->execute($recipe, $household, $this->authenticatedUser($request));

        return HouseholdRecipeResource::make($householdRecipe)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Unshare the given recipe from the household.
     */
    public function unshare(Household $household, Recipe $recipe, UnshareRecipe $action, UnshareHouseholdRecipeRequest $request): Response
    {

        $action->execute($recipe, $household, $this->authenticatedUser($request));

        return response()->noContent();
    }
}
