<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\GenerateRecipeWithAi;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRecipeRequest;
use HomeSide\Saas\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Handles AI-powered recipe generation through the API.
 */
final class RecipeAiController extends Controller
{
    /**
     * Metering: one ai_assistant credit is reserved per accepted request and
     * committed on success / released on failure. v0.1 has no stable business
     * id for a generation attempt, so the operation id is a fresh uuid; HTTP
     * replays are protected by this route's idempotency middleware. All checks
     * are inert while SaaS is disabled.
     */
    public function generate(GenerateRecipeRequest $request, GenerateRecipeWithAi $action): JsonResponse
    {
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
                'message' => 'The AI provider could not produce a valid recipe draft.',
                'code' => 'recipe_generation_failed',
                'errors' => (object) [],
            ], 422);
        } catch (Throwable $exception) {
            $gate->release($operationId);

            throw $exception;
        }

        $gate->commit($operationId);

        return response()->json([
            'data' => $payload,
            'warnings' => [],
        ]);
    }
}
