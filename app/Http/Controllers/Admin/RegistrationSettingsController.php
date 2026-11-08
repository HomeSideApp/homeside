<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HouseholdCreationPolicy;
use App\Enums\InvitationPolicy;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRegistrationSettingsRequest;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Edits the installation-wide registration policy.
 *
 * The controller only moves values between the settings service and the Vue
 * form; every admission decision still happens in the registration gate and in
 * PlatformAccess, so this surface cannot grant a registration or a household by
 * itself.
 */
final class RegistrationSettingsController extends Controller
{
    /**
     * Show the registration-settings form with the persisted values and the
     * options each channel accepts.
     *
     * @param  RegistrationSettings  $settings  The registration settings service.
     * @return Response The HTTP response.
     */
    public function edit(RegistrationSettings $settings): Response
    {
        return Inertia::render('admin/registrations/Settings', [
            'settings' => [
                'web' => $settings->policyFor(RegistrationChannel::Web)->value,
                'api' => $settings->policyFor(RegistrationChannel::Api)->value,
                'google' => $settings->policyFor(RegistrationChannel::Google)->value,
                'household_invitation' => $settings->invitationPolicyFor(RegistrationChannel::HouseholdInvitation)->value,
                'admin_invitation' => $settings->invitationPolicyFor(RegistrationChannel::AdminInvitation)->value,
                'household_creation' => $settings->householdCreationPolicy()->value,
            ],
            'options' => [
                'web' => $this->policyValues($settings->allowedPoliciesFor(RegistrationChannel::Web)),
                'api' => $this->policyValues($settings->allowedPoliciesFor(RegistrationChannel::Api)),
                'google' => $this->policyValues($settings->allowedPoliciesFor(RegistrationChannel::Google)),
                'household_invitation' => $this->enumValues(InvitationPolicy::cases()),
                'admin_invitation' => $this->enumValues(InvitationPolicy::cases()),
                'household_creation' => $this->enumValues(HouseholdCreationPolicy::cases()),
            ],
        ]);
    }

    /**
     * Persist the registration policy.
     *
     * Precognitive requests never reach this method: Laravel resolves the form
     * request and returns 204 before the controller runs, so validating in
     * real time has no side effect.
     *
     * @param  UpdateRegistrationSettingsRequest  $request  The validated request.
     * @param  RegistrationSettings  $settings  The registration settings service.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateRegistrationSettingsRequest $request, RegistrationSettings $settings): RedirectResponse
    {
        /** @var array<string, string> $validated */
        $validated = $request->validated();

        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::from($validated['web']));
        $settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::from($validated['api']));
        $settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::from($validated['google']));

        $settings->setInvitationPolicy(RegistrationChannel::HouseholdInvitation, InvitationPolicy::from($validated['household_invitation']));
        $settings->setInvitationPolicy(RegistrationChannel::AdminInvitation, InvitationPolicy::from($validated['admin_invitation']));

        $settings->setHouseholdCreationPolicy(HouseholdCreationPolicy::from($validated['household_creation']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.registration_settings_updated')]);

        return back();
    }

    /**
     * Reduce a list of registration policies to plain option values.
     *
     * @param  list<RegistrationPolicy>  $policies  The accepted policies.
     * @return list<string> The option values.
     */
    private function policyValues(array $policies): array
    {
        return array_map(fn (RegistrationPolicy $policy): string => $policy->value, $policies);
    }

    /**
     * Reduce enum cases to plain option values.
     *
     * @param  list<InvitationPolicy|HouseholdCreationPolicy>  $cases  The accepted cases.
     * @return list<string> The option values.
     */
    private function enumValues(array $cases): array
    {
        return array_map(fn (InvitationPolicy|HouseholdCreationPolicy $case): string => $case->value, $cases);
    }
}
