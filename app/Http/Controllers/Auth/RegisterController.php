<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\RegisterAccountData;
use App\Data\Auth\RegistrationOutcomeData;
use App\Enums\AppLocale;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAccountRequest;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles public web self-registration.
 *
 * The controller is deliberately thin: it describes the public capabilities to
 * the form and translates the shared use case's typed outcome into redirects.
 * It never decides whether a registration is allowed, never creates a user and
 * never needs to know whether the installation is SaaS or self-hosted.
 */
final class RegisterController extends Controller
{
    /**
     * Show the registration page with the public capabilities.
     *
     * @param  RegistrationSettings  $settings  The registration settings service.
     * @return Response The HTTP response.
     */
    public function show(RegistrationSettings $settings): Response
    {
        return Inertia::render('auth/Register', [
            'capabilities' => $this->capabilities($settings),
        ]);
    }

    /**
     * Run the registration and redirect to the appropriate follow-up.
     *
     * Precognitive requests never reach this method: the form request validates
     * and Laravel returns 204 first, so validation has no side effect.
     *
     * @param  RegisterAccountRequest  $request  The validated request.
     * @param  RegisterAccount  $accounts  The shared registration use case.
     * @param  CreateAuthenticationBootstrap  $bootstraps  The single-use bootstrap issuer.
     * @return RedirectResponse The HTTP response.
     */
    public function store(RegisterAccountRequest $request, RegisterAccount $accounts, CreateAuthenticationBootstrap $bootstraps): RedirectResponse
    {
        $validated = $request->validated();

        $outcome = $accounts->execute(new RegisterAccountData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            locale: $validated['locale'] ?? AppLocale::default()->value,
            channel: RegistrationChannel::Web,
            origin: 'web',
        ));

        if ($outcome->decision === RegistrationDecision::ExistingIdentity) {
            return back()->withErrors(['email' => __('app.errors.registration_identity_exists')])->onlyInput('email');
        }

        if (! $outcome->registered() || $outcome->user === null) {
            return back()->withErrors(['email' => __('app.errors.registration_unavailable')])->onlyInput('email');
        }

        // A pending account is NOT onboarded: it must never receive a usable
        // enrolment path into a session, so no bootstrap is issued for it.
        if ($outcome->nextStep === RegistrationOutcomeData::NEXT_AWAIT_APPROVAL) {
            return to_route('login')->with('status', __('app.auth.registration_pending'));
        }

        // An account that already owns a second factor has nothing to enrol;
        // login is the only remaining step. A fresh web account never reaches
        // this branch, but the invitation flows guard the same way.
        if ($outcome->user->hasEnabledTwoFactorAuthentication()) {
            return to_route('login')->with('status', __('app.auth.registration_verify_email'));
        }

        // Hand out the single-use enrolment bootstrap instead of dropping the
        // visitor on `/login`. Web login always demands a second factor and a
        // freshly registered account has none, so without this the web flow is
        // a dead end. The bootstrap is onboarding only: the session is
        // established at the END of the enrolment flow, and withheld until
        // approval, email verification and account status all allow it.
        //
        // The same issuance mails the link (see CreateAuthenticationBootstrap),
        // so closing the tab is no longer the end of the road.
        return redirect()->to($this->enrolmentUrl($outcome->user, $bootstraps));
    }

    /**
     * Build the single-use enrolment URL for a freshly registered visitor.
     *
     * The lifetime is the enrolment one (12 hours by default), and issuance
     * also mails the link, so the visitor who closes the tab can still finish.
     *
     * @param  User  $user  The account that just registered.
     * @param  CreateAuthenticationBootstrap  $bootstraps  The bootstrap issuer.
     * @return string The redirect target.
     */
    private function enrolmentUrl(User $user, CreateAuthenticationBootstrap $bootstraps): string
    {
        $token = $bootstraps->execute($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        return route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }

    /**
     * Build the public capability payload for the registration page.
     *
     * @param  RegistrationSettings  $settings  The registration settings service.
     * @return array<string, array{available: bool, policy: string, approval_required: bool}> The capability payload.
     */
    private function capabilities(RegistrationSettings $settings): array
    {
        $web = $settings->policyFor(RegistrationChannel::Web);
        $google = $settings->policyFor(RegistrationChannel::Google);
        $googleConfigured = filled(config('services.google.client_id'));

        return [
            'web' => [
                'available' => $web !== RegistrationPolicy::Disabled,
                'policy' => $web->value,
                'approval_required' => $web->requiresApproval(),
            ],
            'google' => [
                'available' => $google !== RegistrationPolicy::Disabled && $googleConfigured,
                'policy' => $google->value,
                'approval_required' => $google->requiresApproval(),
            ],
        ];
    }
}
