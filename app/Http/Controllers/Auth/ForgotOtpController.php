<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotOtpRequest;
use App\Models\User;
use App\Notifications\ResetOtpNotification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the forgot OTP request flow.
 *
 * The transport is a single-use `AccountRecovery` bootstrap token, not a
 * temporary signed URL: the raw token travels in the mailed link, only its
 * keyed digest is persisted, and it is consumed exactly once. A recovery grant
 * can never be redeemed as an enrolment grant (and vice versa).
 */
class ForgotOtpController extends Controller
{
    /**
     * Show the forgot OTP page.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ForgotOtp', [
            'status' => session('status'),
        ]);
    }

    /**
     * Send a single-use OTP recovery link to the given user.
     *
     * The response is identical whether or not the account exists, has a
     * second factor or may use the application: recovery must not become an
     * account oracle. A grant is issued only for an account that could
     * actually redeem it (accessible AND already enrolled), so a blocked or
     * unenrolled identity never receives a usable path.
     *
     * @param  ForgotOtpRequest  $request  The incoming HTTP request.
     * @param  CreateAuthenticationBootstrap  $bootstraps  The single-use bootstrap issuer.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ForgotOtpRequest $request, CreateAuthenticationBootstrap $bootstraps): RedirectResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if ($user !== null && $user->canAccessApplication() && $user->hasEnabledTwoFactorAuthentication()) {
            $token = $bootstraps->execute($user, AuthenticationBootstrapPurpose::AccountRecovery);

            $user->notify(new ResetOtpNotification($user, route('otp.reset', ['token' => $token])));
        }

        return back()->with('status', __('app.auth.otp_reset_link_sent'));
    }
}
