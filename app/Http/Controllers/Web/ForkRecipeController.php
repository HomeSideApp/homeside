<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\ForkRecipe;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(Recipe $recipe, ForkRecipe $action, Request $request): RedirectResponse
    {
        $user = $this->authenticatedUser($request);
        abort_unless($user->can('fork', $recipe), 403);

        $forkedRecipe = $action->execute($recipe, $user);

        return redirect()->route('recipes.show', $forkedRecipe)
            ->with('toast', ['type' => 'success', 'message' => 'Receta duplicada correctamente.']);
    }
}
