<?php

namespace App\Http\Middleware;

use App\Contracts\EntitlementGate;
use App\Models\User;
use App\Support\CapabilityRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Community-owned feature gate: 'entitlement:<feature-key>'.
 *
 * Community owns this middleware and its alias. The commercial package is
 * never referenced from here: it implements App\Contracts\EntitlementGate
 * behind the container binding, so this gate only enforces the port and never
 * needs to know whether that package is installed.
 *
 * Behaviour:
 *  - No authenticated user: the request continues, so the route's own
 *    'auth' / 'auth:sanctum' middleware owns the 401 or login redirect.
 *  - Authenticated user: App\Contracts\EntitlementGate::assertFeature decides,
 *    producing the same 403 with __('app.errors.permission_denied') the
 *    package middleware used.
 *  - Fail closed on unknown keys: when the capability registry declares
 *    features and the route's key is not among them, the request is denied
 *    with a 403 and a warning naming the key, so a typo cannot silently become
 *    an open gate. When the registry declares nothing at all the check is
 *    skipped, so an app with no registry configured does not lock itself out.
 */
final class EnsureEntitlement
{
    public function __construct(
        private readonly EntitlementGate $entitlements,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // Fail closed on a typo'd key, but only when the registry actually
        // declares features; an empty registry must not lock the app out.
        if (! $this->capabilities->hasFeature($feature) && $this->capabilities->features() !== []) {
            Log::warning('Unknown entitlement feature key on a protected route.', [
                'feature' => $feature,
                'route' => $request->route()?->getName() ?? $request->path(),
            ]);

            abort(403, __('app.errors.permission_denied'));
        }

        $this->entitlements->assertFeature($user, $feature);

        return $next($request);
    }
}
