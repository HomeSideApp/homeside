<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorChallengeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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
     * @return RedirectResponse The HTTP response.
     */
    public function store(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $request->loginUser();
        $recoveryCodes = $request->remainingRecoveryCodes();
        if ($recoveryCodes !== null) {
            $user->update(['two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes))]);
        }

        return $this->completeLogin($user, $request);
    }

    /**
     * Log the user in and finalise the session.
     */
    private function completeLogin(User $user, Request $request): RedirectResponse
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->forget('login.id');

        return redirect()->intended('/dashboard');
    }
}
