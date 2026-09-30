<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles web authentication.
 */
class LoginController extends Controller
{
    /**
     * Show the login page.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  LoginRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = $this->authenticatedUser($request);

            if (! $user->canAccessApplication()) {
                Auth::logout();

                return back()->withErrors(['email' => 'Tu cuenta no está aprobada o no tiene un rol asignado.'])->onlyInput('email');
            }

            // Si el usuario no tiene 2FA configurado, denegar login
            if (! $user->hasEnabledTwoFactorAuthentication()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => __('app.auth.account_without_otp'),
                ])->onlyInput('email');
            }

            // Redirigir al desafío OTP
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('login.id', $user->id);

            return redirect()->route('two-factor.login');
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
