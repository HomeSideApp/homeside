<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\ForkRecipe;
use App\Http\Controllers\Controller;
use App\Http\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ForkRecipeController
 *
 * This controller handles the API endpoint for forking a recipe, creating a new
 * copy owned by the authenticated user.
 */
final class ForkRecipeController extends Controller
{
    /**
     * Fork the given recipe for the authenticated user.
     */
    public function store(Recipe $recipe, ForkRecipe $action, Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorize('fork', $recipe);
        $forkedRecipe = $action->execute($recipe, $user);

        return RecipeResource::make($forkedRecipe)
            ->response()
            ->setStatusCode(201);
    }
}
