<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\GenerateRecipeWithAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRecipeRequest;
use HomeSide\Saas\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Handles AI-powered recipe generation.
 */
final class RecipeAiController extends Controller
{
    /**
     * Generate a recipe from a free-text prompt.
     *
     * Metering: one ai_assistant credit is reserved per accepted request and
     * committed on success / released on failure. v0.1 has no stable business
     * id for a generation attempt, so the operation id is a fresh uuid; HTTP
     * replays on the API surface are protected by the existing idempotency
     * middleware. All checks are inert while SaaS is disabled.
     *
     * @param  GenerateRecipeRequest  $request  The incoming HTTP request.
     * @param  GenerateRecipeWithAi  $action  The recipe generation action.
     * @return JsonResponse The JSON response.
     */
    public function generate(
        GenerateRecipeRequest $request,
        GenerateRecipeWithAi $action,
    ): JsonResponse {
        $user = $this->authenticatedUser($request);
        $gate = app(FeatureGate::class);

        $gate->assertFeature($user, 'ai_assistant');

        $operationId = 'recipeai:'.(string) Str::uuid();
        $gate->reserve($user, (string) config('saas.metrics.ai_assistant', 'ai_assistant_monthly'), 1, $operationId);

        try {
            $payload = $action->execute($user, $request->validated('prompt'));
        } catch (RuntimeException $exception) {
            $gate->release($operationId);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            $gate->release($operationId);

            throw $exception;
        }

        $gate->commit($operationId);

        return response()->json($payload);
    }
}
