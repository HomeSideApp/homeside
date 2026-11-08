<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
 * Handles AI-powered recipe generation through the API.
 */
final class RecipeAiController extends Controller
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * Metering: one ai_assistant credit is reserved per accepted request and
     * committed on success / released on failure. v0.1 has no stable business
     * id for a generation attempt, so the caller id is a fresh uuid used only
     * as an idempotency hint; HTTP replays are protected by this route's
     * idempotency middleware. The reservation's canonical id is returned by the
     * port and is the only value handed to commit/release. All checks are inert
     * while SaaS is disabled.
     */
    public function generate(GenerateRecipeRequest $request, GenerateRecipeWithAi $action): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $this->entitlements->assertFeature($user, 'ai_assistant');

        $callerOperationId = 'recipeai:'.(string) Str::uuid();
        $canonicalOperationId = $this->entitlements->reserveUsage($user, (string) ($this->capabilities->metric('ai_assistant') ?? 'ai_assistant_monthly'), 1, $callerOperationId);

        try {
            $payload = $action->execute($user, $request->validated('prompt'));
        } catch (RuntimeException $exception) {
            $this->entitlements->releaseUsage($canonicalOperationId);

            return response()->json([
                'message' => 'The AI provider could not produce a valid recipe draft.',
                'code' => 'recipe_generation_failed',
                'errors' => (object) [],
            ], 422);
        } catch (Throwable $exception) {
            $this->entitlements->releaseUsage($canonicalOperationId);

            throw $exception;
        }

        $this->entitlements->commitUsage($canonicalOperationId);

        return response()->json([
            'data' => $payload,
            'warnings' => [],
        ]);
    }
}
