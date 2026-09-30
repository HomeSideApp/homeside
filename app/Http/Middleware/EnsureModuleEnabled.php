<?php

namespace App\Http\Middleware;

use App\Models\Household;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureModuleEnabled
{
    /**
     * Handle an incoming request.
     *
     * Checks if the specified module is enabled for the current household.
     * Aborts with 404 if the module is disabled.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $module  The module name to check (e.g. 'shopping_lists', 'recipes')
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $household = $this->resolveHousehold($request);

        if (! $household) {
            abort(404, 'Household not found');
        }

        $isEnabled = $household->modules()
            ->where('module', $module)
            ->where('enabled', true)
            ->exists();

        if (! $isEnabled) {
            abort(404, 'This module is not enabled for this household');
        }

        return $next($request);
    }

    private function resolveHousehold(Request $request): ?Household
    {
        // 1. From route parameter
        $routeHousehold = $request->route('household');
        if ($routeHousehold instanceof Household) {
            return $routeHousehold;
        }
        if (is_string($routeHousehold)) {
            return Household::find($routeHousehold);
        }

        // 2. From request attributes (if EnsureUserHasHousehold already ran)
        $fromAttributes = $request->attributes->get('household');
        if ($fromAttributes instanceof Household) {
            return $fromAttributes;
        }

        // 3. Fallback to active_household_id
        $user = $request->user();
        if ($user?->active_household_id) {
            return Household::find($user->active_household_id);
        }

        return null;
    }
}
