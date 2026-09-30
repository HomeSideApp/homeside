<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\ListHouseholdRecipes;
use App\Actions\Recipes\ShareRecipe;
use App\Actions\Recipes\UnshareRecipe;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles recipes shared with households.
 */
final class HouseholdRecipeController extends Controller
{
    /**
     * List the recipes shared with a household.
     *
     * @param  Household  $household  The household model instance.
     * @param  ListHouseholdRecipes  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Household $household, ListHouseholdRecipes $action, Request $request): Response
    {
        $filters = $request->only(['search']);

        return Inertia::render('recipes/HouseholdIndex', [
            'household' => $household,
            'householdRecipes' => fn () => $action->execute($household, $filters),
            'filters' => $filters,
        ]);
    }

    public function share(Household $household, Recipe $recipe, ShareRecipe $action, Request $request): RedirectResponse
    {
        $action->execute($recipe, $household, $this->authenticatedUser($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Receta compartida con el hogar.']);
    }

    public function unshare(Household $household, Recipe $recipe, UnshareRecipe $action): RedirectResponse
    {
        $action->execute($recipe, $household);

        return back()->with('toast', ['type' => 'success', 'message' => __('app.toast.recipe_unshared')]);
    }
}
