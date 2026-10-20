<?php

namespace App\Http\Controllers;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Actions\Households\RegisterViaInviteLink;
use App\Data\Households\RegisterViaInviteLinkData;
use App\Http\Requests\RegisterViaInviteLinkRequest;
use App\Http\Requests\ViewInviteLinkRequest;
use App\Models\HouseholdInviteLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the public registration page for reusable household invite links.
 */
class InviteLinkController extends Controller
{
    /**
     * Show the registration page for an invite link.
     *
     * @param  string  $token  The public invite link token.
     * @return Response The HTTP response.
     */
    public function show(ViewInviteLinkRequest $request, string $token): Response
    {
        $link = $request->inviteLink();

        $household = $link->household()->firstOrFail();

        return Inertia::render('auth/SetPassword', [
            'mode' => 'link',
            'token' => $link->token,
            'email' => '',
            'name' => '',
            'householdName' => $household->name,
            'storeUrl' => route('invite-links.store', $link->token),
        ]);
    }

    /**
     * Register a new verified user through an invite link.
     *
     * @param  RegisterViaInviteLinkRequest  $request  The incoming HTTP request.
     * @param  string  $token  The public invite link token.
     * @param  RegisterViaInviteLink  $action  The action responsible for the operation.
     * @param  CreateAuthenticationBootstrap  $bootstraps  The single-use bootstrap issuer.
     * @return RedirectResponse The HTTP response.
     */
    public function store(RegisterViaInviteLinkRequest $request, string $token, RegisterViaInviteLink $action, CreateAuthenticationBootstrap $bootstraps): RedirectResponse
    {
        $link = HouseholdInviteLink::query()->where('token', $token)->firstOrFail();

        $outcome = $action->execute(RegisterViaInviteLinkData::fromArray($request->validated()), $link, 'web');

        // An existing account is never taken over by an invitation: the owner
        // must authenticate as it.
        if (! $outcome->registered() || $outcome->user === null) {
            throw ValidationException::withMessages([
                'email' => __('app.errors.registration_identity_exists'),
            ]);
        }

        return redirect()->to($this->enrolmentUrl($outcome->user, $bootstraps));
    }

    /**
     * Build the single-use enrolment URL for a freshly registered invitee.
     *
     * The old signed `otp.setup` URL is retired: the bootstrap token is bound
     * to the user AND the enrolment purpose, lives for the enrolment lifetime
     * (12 hours by default) and can be consumed exactly once, so a link that
     * leaks stops being a session. Issuance also mails the link, so an invitee
     * who closes the tab can still finish.
     *
     * @param  User  $user  The account that just registered.
     * @param  CreateAuthenticationBootstrap  $bootstraps  The bootstrap issuer.
     * @return string The redirect target.
     */
    private function enrolmentUrl(User $user, CreateAuthenticationBootstrap $bootstraps): string
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return route('login');
        }

        $token = $bootstraps->execute($user);

        return route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }
}
