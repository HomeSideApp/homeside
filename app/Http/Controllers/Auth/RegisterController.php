<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\RegisterAccountData;
use App\Data\Auth\RegistrationOutcomeData;
use App\Enums\AppLocale;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAccountRequest;
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
     * @return RedirectResponse The HTTP response.
     */
    public function store(RegisterAccountRequest $request, RegisterAccount $accounts): RedirectResponse
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

        if ($outcome->nextStep === RegistrationOutcomeData::NEXT_AWAIT_APPROVAL) {
            return to_route('login')->with('status', __('app.auth.registration_pending'));
        }

        return to_route('login')->with('status', __('app.auth.registration_verify_email'));
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
