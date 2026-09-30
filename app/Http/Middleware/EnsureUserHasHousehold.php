<?php

namespace App\Http\Middleware;

use App\Models\Household;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasHousehold
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->households_enabled) {
            return redirect()->route('dashboard');
        }

        $exemptRoutes = [
            'households.select',
            'households.create',
            'households.store',
            'households.accept',
            'households.invitations.accept',
            'households.invitations.cancel',
            'logout',
            'password.confirm',
            'profile.show',
            'profile.edit',
            'two-factor',
        ];

        $currentRoute = $request->route()?->getName();

        if ($currentRoute && in_array($currentRoute, $exemptRoutes)) {
            return $next($request);
        }

        // 1. Leer household del parámetro de ruta
        //    Nota: route model binding ya resolvió el modelo antes de este middleware
        $routeHousehold = $request->route('household');

        if ($routeHousehold instanceof Household) {
            // Route model binding ya resolvió el modelo
            $household = $routeHousehold;
        } elseif ($routeHousehold) {
            // Es un string UUID, buscar el modelo
            $household = Household::query()->whereKey($routeHousehold)->first();
        } else {
            // 2. Fallback a active_household_id en BD
            $household = null;
            $activeHouseholdId = $user->active_household_id;

            if ($activeHouseholdId) {
                $household = Household::query()->whereKey($activeHouseholdId)->first();
            }
        }

        if (! $household || ! $user->isMemberOf($household)) {
            // Si el hogar inválido era el activo, limpiarlo
            if ($user->active_household_id && $household && $household->id === $user->active_household_id) {
                $user->update(['active_household_id' => null]);
            }

            return redirect()->route('households.select');
        }

        // Almacenar en request attributes para controllers
        $request->attributes->set('household', $household);

        return $next($request);
    }
}
