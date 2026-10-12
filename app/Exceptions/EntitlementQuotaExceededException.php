<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when a usage reservation would exceed the user's quota for a metric.
 *
 * Self-renderable: API/JSON requests get a 402 envelope matching the app's
 * error shape; plain web requests get a back-redirect carrying a 'saas' flash
 * message.
 */
class EntitlementQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $userId,
        public readonly string $metric,
        public readonly int $cap,
        public readonly int $used,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Quota [%s] exceeded for user [%s]: used %d of %d, requested %d.',
            $metric,
            $userId,
            $used,
            $cap,
            $requested,
        ));
    }

    /**
     * Render the quota denial for HTTP surfaces.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        $message = sprintf(
            'You have reached your plan\'s quota: %d of %d already used for this billing period.',
            $this->used,
            $this->cap,
        );

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code' => 'quota_exceeded',
                'errors' => (object) [],
            ], 402);
        }

        return redirect()->back()->with('saas', $message);
    }
}
