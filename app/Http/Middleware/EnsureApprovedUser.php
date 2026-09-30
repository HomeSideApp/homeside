<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $isApi = $request->is('api/*');
        $user = $isApi ? $request->user('sanctum') : $request->user();
        if ($user === null) {
            return $next($request);
        }

        if (! $user->canAccessApplication()) {
            if (! $isApi) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'Tu cuenta está pendiente de aprobación o no tiene un rol asignado.');
            }

            abort(403, 'Tu cuenta no está aprobada.');
        }

        if ($isApi) {
            $token = $user->currentAccessToken();
            $temporary = $token !== null && in_array($token->name, ['temp-otp', 'google-otp-setup'], true);
            $allowedTemporaryRoutes = [
                'api.v1.auth.login.otp',
                'api.v1.auth.login.recovery-code',
                'api.v1.auth.login.otp.resend',
                'api.v1.auth.google.otp.setup',
                'api.v1.auth.google.otp.confirm',
                'api.v1.auth.logout',
            ];
            if ($temporary && ! in_array($request->route()?->getName(), $allowedTemporaryRoutes, true)) {
                abort(403, 'El token temporal no permite acceder a este recurso.');
            }
        }

        return $next($request);
    }
}
