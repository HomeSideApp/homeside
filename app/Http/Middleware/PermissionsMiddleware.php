<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class PermissionsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Check whether the authenticated user has the permission explicitly
     * assigned to the middleware, falling back to the current route permission
     * when the explicit permission has not been configured.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): Response  $next  The next middleware in the request pipeline.
     * @param  string|null  $permissionRouteName  The preferred route name that identifies the required permission, or null to use the current route name.
     * @return Response The response returned by the next middleware when access is granted.
     */
    public function handle(Request $request, Closure $next, ?string $permissionRouteName = null): Response
    {
        $currentRouteName = Route::currentRouteName();
        $permissionRouteNames = array_values(array_unique(array_filter([
            $permissionRouteName,
            $currentRouteName,
        ])));

        if ($permissionRouteNames === []) {
            abort(404, __('app.errors.route_permission_not_configured'));
        }

        $cacheKey = 'permission:route:'.implode('|', $permissionRouteNames);
        $permissionId = Cache::remember($cacheKey, now()->addDay(), function () use ($permissionRouteNames) {
            foreach ($permissionRouteNames as $routeName) {
                $permissionId = Permission::where('route_name', $routeName)->value('id');

                if ($permissionId !== null) {
                    return $permissionId;
                }
            }

            return null;
        });

        if (! $permissionId) {
            abort(404, __('app.errors.route_permission_not_configured'));
        }

        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $hasPermission = $user->roles()
            ->whereHas('permissions', function ($query) use ($permissionId) {
                $query->where('permissions.id', $permissionId);
            })
            ->exists();

        if (! $hasPermission) {
            abort(403, __('app.errors.permission_denied'));
        }

        return $next($request);
    }
}
