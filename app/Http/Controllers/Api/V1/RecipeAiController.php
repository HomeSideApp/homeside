<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\GenerateRecipeWithAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRecipeRequest;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Handles AI-powered recipe generation through the API.
 */
final class RecipeAiController extends Controller
{
    public function generate(GenerateRecipeRequest $request, GenerateRecipeWithAi $action): JsonResponse
    {
        try {
            return response()->json([
                'data' => $action->execute(
                    $this->authenticatedUser($request),
                    $request->validated('prompt'),
                ),
                'warnings' => [],
            ]);
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'The AI provider could not produce a valid recipe draft.',
                'code' => 'recipe_generation_failed',
                'errors' => (object) [],
            ], 422);
        }
    }
}
