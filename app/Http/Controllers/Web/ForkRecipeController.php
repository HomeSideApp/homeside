<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\ForkRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForkRecipeRequest;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;

/**
 * Forks a recipe into the user's own cookbook.
 */
final class ForkRecipeController extends Controller
{
    /**
     * Create a forked copy of a recipe.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  ForkRecipe  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(Recipe $recipe, ForkRecipe $action, ForkRecipeRequest $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);

        $forkedRecipe = $action->execute($recipe, $user);

        return redirect()->route('recipes.show', $forkedRecipe)
            ->with('toast', ['type' => 'success', 'message' => 'Receta duplicada correctamente.']);
    }
}
