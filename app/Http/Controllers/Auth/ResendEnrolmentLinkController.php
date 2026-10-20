<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResendEnrolmentByEmailRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the public "I do not have an enrolment link" flow.
 *
 * This is the second, token-free way back to a two-factor enrolment. It exists
 * because the first way — opening the link you were mailed — fails the moment
 * the mail is gone, and `otp/forgot` cannot help: it requires the account to
 * already own a second factor, which is exactly what the visitor was setting
 * up.
 *
 * It is deliberately NOT an account oracle:
 *
 *  - the response is identical whether the address exists, already has a second
 *    factor, is blocked or is unknown;
 *  - nothing is minted or mailed unless the account exists, has no second factor
 *    and may use the application, because those are the only accounts that could
 *    redeem the link;
 *  - validation only checks the shape of the address, never its existence, so a
 *    validation error cannot be used to probe accounts either.
 *
 * It does not share a path with `otp/forgot`: that flow issues an
 * `AccountRecovery` grant and keeps its "you must already have a factor" guard,
 * while this one issues a `TwoFactorEnrolment` grant. The two purposes carry
 * different digests, so neither token can be redeemed as the other.
 */
class ResendEnrolmentLinkController extends Controller
{
    /**
     * Show the email form.
     *
     * @return Response The HTTP response.
     */
    public function show(): Response
    {
        return Inertia::render('auth/ResendEnrolmentLink', [
            'mode' => 'request',
            'resendUrl' => null,
            'requestUrl' => route('auth.enrolment.resend.store'),
            'status' => session('status'),
        ]);
    }

    /**
     * Mail a fresh enrolment link when the account can actually use one.
     *
     * The same redirect and the same flashed message are returned in every
     * branch, so the page cannot be used to learn whether an address exists,
     * already enrolled, or is blocked.
     *
     * @param  ResendEnrolmentByEmailRequest  $request  The validated request.
     * @param  CreateAuthenticationBootstrap  $create  The single-use bootstrap issuer.
     * @return RedirectResponse The HTTP response.
     */
    public function store(ResendEnrolmentByEmailRequest $request, CreateAuthenticationBootstrap $create): RedirectResponse
    {
        $user = User::query()->where('email', $request->normalisedEmail())->first();

        // Only an account that could redeem the link is allowed to produce one.
        // A missing account, an account that already has a factor (that is the
        // `otp/forgot` path) and an account without access all stay silent.
        if ($user !== null
            && ! $user->hasEnabledTwoFactorAuthentication()
            && $user->canAccessApplication()) {
            $create->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
        }

        return to_route('auth.enrolment.resend')->with('status', __('app.auth.enrolment_link_sent'));
    }
}
