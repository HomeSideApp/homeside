<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles the two-factor authentication challenge during login.
 */
class TwoFactorChallengeController extends Controller
{
    /**
     * Show the 2FA challenge page.
     *
     * @return Response|RedirectResponse The HTTP response.
     */
    public function show(): Response|RedirectResponse
    {
        if (! session('login.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/TwoFactorChallenge');
    }

    /**
     * Verify the OTP or recovery code and complete the login.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(Request $request): RedirectResponse
    {
        $loginId = session('login.id');

        if (! $loginId) {
            return redirect()->route('login');
        }

        $user = User::query()->whereKey($loginId)->first();

        if (! $user || ! $user->canAccessApplication()) {
            $request->session()->forget('login.id');

            return redirect()->route('login');
        }

        // Verificar código OTP
        if ($request->filled('code')) {
            if ($user->two_factor_secret === null) {
                return redirect()->route('login');
            }

            $google2fa = new Google2FA;
            $valid = $google2fa->verifyKey(
                decrypt($user->two_factor_secret),
                $request->input('code')
            );

            if ($valid) {
                return $this->completeLogin($user, $request);
            }

            return back()->withErrors([
                'code' => __('app.auth.two_factor_code_invalid'),
            ]);
        }

        // Verificar código de recuperación
        if ($request->filled('recovery_code')) {
            if ($user->two_factor_recovery_codes === null) {
                return redirect()->route('login');
            }

            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (! is_array($recoveryCodes)) {
                return redirect()->route('login');
            }

            if (in_array($request->input('recovery_code'), $recoveryCodes)) {
                // Eliminar el código de recuperación usado
                $recoveryCodes = array_values(array_diff($recoveryCodes, [$request->input('recovery_code')]));
                $user->update([
                    'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
                ]);

                return $this->completeLogin($user, $request);
            }

            return back()->withErrors([
                'recovery_code' => __('app.auth.two_factor_recovery_code_invalid'),
            ]);
        }

        return back()->withErrors([
            'code' => __('app.auth.two_factor_code_required'),
        ]);
    }

    /**
     * Log the user in and finalise the session.
     */
    private function completeLogin(User $user, Request $request): RedirectResponse
    {
        abort_unless($user->canAccessApplication(), 403);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('login.id');

        return redirect()->intended('/dashboard');
    }
}
