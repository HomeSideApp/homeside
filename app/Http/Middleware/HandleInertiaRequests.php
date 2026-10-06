<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Enums\HouseholdAccessProfile;
use App\Http\Resources\Households\HouseholdInvitationResource;
use App\Http\Resources\Households\HouseholdResource;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Support\HouseholdAccess;
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
            'householdAccessDefaults' => fn () => collect(HouseholdAccessProfile::cases())
                ->mapWithKeys(fn (HouseholdAccessProfile $profile): array => [$profile->value => $profile->permissions()])
                ->all(),
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
                'capabilities' => fn () => ($user && ($household = $this->resolveActiveHousehold($request, $user))) ? app(HouseholdAccess::class)->capabilities($user, $household) : [],
                'canManage' => fn () => ($user && ($household = $this->resolveActiveHousehold($request, $user))) ? app(HouseholdAccess::class)->manages($user, $household) : false,
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
            return app(HouseholdAccess::class)->membership($user, $fromAttributes) !== null ? $fromAttributes : null;
        }

        // 2. Desde el parámetro de ruta (route model binding)
        $routeHousehold = $request->route('household');
        if ($routeHousehold instanceof Household) {
            return app(HouseholdAccess::class)->membership($user, $routeHousehold) !== null ? $routeHousehold : null;
        }
        if (is_string($routeHousehold)) {
            return app(HouseholdAccess::class)->membership($user, $routeHousehold)?->household;
        }

        // 3. Fallback a active_household_id en BD
        if ($user->active_household_id) {
            return app(HouseholdAccess::class)->membership($user, $user->active_household_id)?->household;
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
