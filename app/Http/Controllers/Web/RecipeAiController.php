<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\GenerateRecipeWithAi;
use App\Contracts\EntitlementGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateRecipeRequest;
use App\Support\CapabilityRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Handles AI-powered recipe generation.
 */
final class RecipeAiController extends Controller
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * Generate a recipe from a free-text prompt.
     *
     * Metering: one ai_assistant credit is reserved per accepted request and
     * committed on success / released on failure. v0.1 has no stable business
     * id for a generation attempt, so the caller id is a fresh uuid used only
     * as an idempotency hint; HTTP replays on the API surface are protected by
     * the existing idempotency middleware. The reservation's canonical id is
     * returned by the port and is the only value handed to commit/release.
     * All checks are inert while SaaS is disabled.
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

        $this->entitlements->assertFeature($user, 'ai_assistant');

        $callerOperationId = 'recipeai:'.(string) Str::uuid();
        $canonicalOperationId = $this->entitlements->reserveUsage($user, (string) ($this->capabilities->metric('ai_assistant') ?? 'ai_assistant_monthly'), 1, $callerOperationId);

        try {
            $payload = $action->execute($user, $request->validated('prompt'));
        } catch (RuntimeException $exception) {
            $this->entitlements->releaseUsage($canonicalOperationId);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable $exception) {
            $this->entitlements->releaseUsage($canonicalOperationId);

            throw $exception;
        }

        $this->entitlements->commitUsage($canonicalOperationId);

        return response()->json($payload);
    }
}
