<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class ApiPermissionsMiddleware
{
    /**
     * API routes whose capability is protected by an existing web permission.
     *
     * @var array<string, string>
     */
    public const array PERMISSION_ALIASES = [
        'categories.store' => 'admin.categories.store',
        'categories.update' => 'admin.categories.update',
        'categories.destroy' => 'admin.categories.destroy',
        'products.store' => 'households.products.store',
        'products.update' => 'admin.products.update',
        'products.destroy' => 'admin.products.destroy',
        'products.quick-create' => 'households.products.quick-create',
        'households.image' => 'households.show',
        'households.settings.update' => 'households.update',
        'households.lists.items.image' => 'households.lists.show',
        'households.lists.items.quick-create' => 'households.lists.items.store',
        'lists.index' => 'households.lists.index',
        'lists.store' => 'households.lists.store',
        'lists.show' => 'households.lists.show',
        'lists.update' => 'households.lists.update',
        'lists.destroy' => 'households.lists.destroy',
        'lists.items.store' => 'households.lists.items.store',
        'lists.items.update' => 'households.lists.items.update',
        'lists.items.destroy' => 'households.lists.items.destroy',
        'lists.items.image' => 'households.lists.show',
        'lists.items.quick-create' => 'households.lists.items.store',
        'households.ai-providers.show' => 'households.ai-providers.index',
        'households.ai-providers.default' => 'households.ai-providers.update',
        'households.ai-providers.module-config' => 'households.ai-providers.update',
        'households.economy.documents.file' => 'households.economy.documents.show',
        'households.economy.totals' => 'households.economy.transactions.index',
        'households.economy.overview' => 'households.economy.transactions.index',
        'households.dashboard' => 'dashboard',
        'households.configuration.show' => 'households.store',
        'households.stores.index' => 'households.lists.index',
        'economy.me.documents.file' => 'economy.me.documents.show',
        'economy.me.transactions.patch' => 'economy.me.transactions.update',
        'economy.me.overview' => 'economy.me.index',
        'households.economy.transactions.patch' => 'households.economy.transactions.update',
        'admin.users.index' => 'admin.users',
        'admin.users.show' => 'admin.users',
        'admin.users.approve' => 'admin.users.update',
        'admin.users.reject' => 'admin.users.update',
        'admin.roles.index' => 'admin.roles',
        'admin.translations.index' => 'admin.translations',
        'admin.permissions.index' => 'admin.roles',
        'admin.ai-providers.index' => 'admin.ai-providers',
        'admin.ai-providers.show' => 'admin.ai-providers',
        'admin.ai-modules.index' => 'admin.ai-providers',
        'admin.ai-prompts.index' => 'admin.ai-providers',
        'admin.ai-prompts.update' => 'admin.ai-settings.module-prompt',
        'admin.ai-usage.index' => 'admin.ai-providers',
        'admin.products.pending.index' => 'admin.products.pending',
        'admin.products.images.generate' => 'admin.products.generate-images',
        'admin.product-image-runs.show' => 'admin.products.generate-images',
        'recipes.image' => 'recipes.show',
        'recipes.image.store' => 'recipes.update',
        'recipes.image.destroy' => 'recipes.update',
        'recipes.steps.image' => 'recipes.show',
        'recipes.steps.image.store' => 'recipes.update',
        'recipes.steps.image.destroy' => 'recipes.update',
        'recipes.collections.index' => 'recipes.index',
        'recipes.collections.store' => 'recipes.store',
        'recipes.collections.update' => 'recipes.update',
        'recipes.collections.destroy' => 'recipes.destroy',
        'recipes.ai-generate' => 'recipes.store',
        'jobs-monitor.statistics' => 'jobs-monitor.index',
        'jobs-monitor.queue-depth' => 'jobs-monitor.index',
        'jobs-monitor.failed' => 'jobs-monitor.jobs',
        'jobs-monitor.jobs-by-tag' => 'jobs-monitor.jobs',
    ];

    /**
     * Handle an incoming request.
     *
     * Resolves API route names to their web equivalents so the same
     * permissions cover both Web and API routes. Uses cached lookups.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): Response  $next  The next middleware in the request pipeline.
     * @param  string|null  $permissionRouteName  An explicit web permission route name, when the API name cannot be inferred.
     * @return Response The response returned by the next middleware.
     */
    public function handle(Request $request, Closure $next, ?string $permissionRouteName = null): Response
    {
        $routeName = Route::currentRouteName();
        $webRouteName = preg_replace('/^api\.v1\./', '', $routeName ?? '');
        $permissionRouteNames = array_values(array_unique(array_filter([
            $permissionRouteName,
            self::PERMISSION_ALIASES[$webRouteName] ?? null,
            $webRouteName,
        ])));

        if ($permissionRouteNames === []) {
            abort(404, __('app.errors.route_permission_not_configured'));
        }

        $cacheKey = 'permission:api-route:'.implode('|', $permissionRouteNames);
        $permissionId = Cache::remember($cacheKey, now()->addDay(), function () use ($permissionRouteNames) {
            foreach ($permissionRouteNames as $candidateRouteName) {
                $permissionId = Permission::query()
                    ->where('route_name', $candidateRouteName)
                    ->value('id');

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
