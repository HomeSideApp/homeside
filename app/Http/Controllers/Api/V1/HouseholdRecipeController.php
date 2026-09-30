<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\ListHouseholdRecipes;
use App\Actions\Recipes\ShareRecipe;
use App\Actions\Recipes\UnshareRecipe;
use App\Http\Controllers\Controller;
use App\Http\Resources\Households\HouseholdRecipeResource;
use App\Models\Household;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(Household $household, ListHouseholdRecipes $action, Request $request): AnonymousResourceCollection
    {
        $this->authorize('view', $household);
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'tag' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);
        $recipes = $action->execute($household, $filters);

        return HouseholdRecipeResource::collection($recipes);
    }

    /**
     * Share the given recipe with the household.
     */
    public function share(Household $household, Recipe $recipe, ShareRecipe $action, Request $request): JsonResponse
    {
        $this->authorize('view', $household);
        $this->authorize('share', $recipe);
        $householdRecipe = $action->execute($recipe, $household, $this->authenticatedUser($request));

        return HouseholdRecipeResource::make($householdRecipe)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Unshare the given recipe from the household.
     */
    public function unshare(Household $household, Recipe $recipe, UnshareRecipe $action, Request $request): Response
    {
        $this->authorize('view', $household);

        if (! $household->isAdmin($this->authenticatedUser($request))) {
            $this->authorize('unshare', $recipe);
        }

        $action->execute($recipe, $household);

        return response()->noContent();
    }
}
