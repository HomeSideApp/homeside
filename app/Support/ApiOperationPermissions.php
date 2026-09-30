<?php

namespace App\Support;

use App\Http\Middleware\ApiPermissionsMiddleware;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class ApiOperationPermissions
{
    /**
     * Return the protected OpenAPI operation IDs available to a user.
     *
     * @return Collection<int, string>
     */
    public static function for(User $user): Collection
    {
        $permissionRouteNames = $user->getPermissionRouteNames();

        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => Str::startsWith((string) $route->getName(), 'api.v1.'))
            ->filter(fn (Route $route): bool => in_array('auth:sanctum', $route->middleware(), true))
            ->filter(function (Route $route) use ($permissionRouteNames): bool {
                if (! in_array('api.permission', $route->middleware(), true)) {
                    return true;
                }

                $domainRouteName = Str::after((string) $route->getName(), 'api.v1.');
                $candidates = array_filter([
                    $domainRouteName,
                    ApiPermissionsMiddleware::PERMISSION_ALIASES[$domainRouteName] ?? null,
                ]);

                return $permissionRouteNames->intersect($candidates)->isNotEmpty();
            })
            ->map(fn (Route $route): string => Str::after((string) $route->getName(), 'api.'))
            ->unique()
            ->sort()
            ->values();
    }
}
