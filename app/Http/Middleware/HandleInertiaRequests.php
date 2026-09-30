<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Http\Resources\Households\HouseholdInvitationResource;
use App\Http\Resources\Households\HouseholdResource;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Determine the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return ?string A string value.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array An array of data.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'localization' => [
                'locale' => app()->getLocale(),
                'fallbackLocale' => config('app.fallback_locale'),
                'supportedLocales' => AppLocale::options(),
            ],
            'auth' => [
                'user' => $user,
                'permissions' => fn () => $user
                    ? $user->getPermissionRouteNames()->toArray()
                    : [],
                'roles' => fn () => $user
                    ? $user->getRoleNames()->toArray()
                    : [],
            ],
            'householdContext' => [
                'active' => fn () => $this->resolveActiveHousehold($request, $user),
                'enabledModules' => fn () => $this->resolveEnabledModules($request, $user),
                'households' => fn () => $user?->households_enabled
                    ? HouseholdResource::collection(
                        $user->households()->withCount('members')->with('modules')->get()
                    )->resolve()
                    : [],
            ],
            'pendingInvitations' => fn () => $user?->households_enabled
                ? HouseholdInvitationResource::collection(
                    HouseholdInvitation::where('email', $user->email)
                        ->pending()
                        ->with('household')
                        ->get()
                )->resolve()
                : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Resolve the active household from request attributes, route parameter, or database.
     */
    private function resolveActiveHousehold(Request $request, ?User $user): ?Household
    {
        if (! $user || ! $user->households_enabled) {
            return null;
        }

        // 1. Desde request attributes (si EnsureUserHasHousehold ya ejecutó)
        $fromAttributes = $request->attributes->get('household');
        if ($fromAttributes instanceof Household) {
            return $fromAttributes;
        }

        // 2. Desde el parámetro de ruta (route model binding)
        $routeHousehold = $request->route('household');
        if ($routeHousehold instanceof Household) {
            return $routeHousehold;
        }
        if (is_string($routeHousehold)) {
            return Household::query()->whereKey($routeHousehold)->first();
        }

        // 3. Fallback a active_household_id en BD
        if ($user->active_household_id) {
            return Household::query()->whereKey($user->active_household_id)->first();
        }

        return null;
    }

    /**
     * Resolve the enabled module names for the active household.
     *
     * @return list<string>
     */
    private function resolveEnabledModules(Request $request, ?User $user): array
    {
        $household = $this->resolveActiveHousehold($request, $user);

        if (! $household) {
            return [];
        }

        return $household->modules()
            ->where('enabled', true)
            ->pluck('module')
            ->map(fn (mixed $module): string => (string) $module)
            ->values()
            ->all();
    }
}
