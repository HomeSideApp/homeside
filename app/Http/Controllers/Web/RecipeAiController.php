<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\GenerateRecipeWithAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRecipeRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Handles AI-powered recipe generation.
 */
final class RecipeAiController extends Controller
{
    /**
     * Generate a recipe from a free-text prompt.
     *
     * @param  GenerateRecipeRequest  $request  The incoming HTTP request.
     * @param  GenerateRecipeWithAi  $action  The recipe generation action.
     * @return JsonResponse The JSON response.
     */
    public function generate(
        GenerateRecipeRequest $request,
        GenerateRecipeWithAi $action,
    ): JsonResponse {
        try {
            return response()->json($action->execute(
                $this->authenticatedUser($request),
                $request->validated('prompt'),
            ));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
