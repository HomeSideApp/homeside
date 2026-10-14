<?php

namespace App\Services\Auth;

use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;

/**
 * The secure self-hosted fallback policies.
 *
 * These values are used only when `platform_settings` holds no row for a
 * policy-gated channel (see {@see RegistrationPolicyDefaults}). A stored row
 * always wins, so this class never overrides an operator's decision. It also
 * never decides capacity or authorisation; it only names the opening posture of
 * a fresh install.
 *
 * Defaults and their rationale:
 *
 *  - Web registration: `disabled`. A password form is the easiest channel to
 *    abuse on a public host, so it stays off until the operator asks for it.
 *  - API registration: `disabled`. Same reasoning, and the endpoint is
 *    reachable without a browser.
 *  - Google registration: `approval_required`. Federated sign-in is convenient,
 *    but the identity is external, so an administrator decides.
 *  - Household invitations: `enabled`. Inviting people is the normal onboarding
 *    path of a (private) self-hosted install, and the invitation itself carries
 *    the operator's decision, so no extra gate is needed.
 *  - Admin invitations: `enabled`. An administrator already has the authority
 *    to create accounts; refusing the flow by default only breaks onboarding.
 *
 * Invitation channels are not policy-gated: `RegistrationSettings` resolves
 * them through `InvitationPolicy` and never consults this class for them. The
 * `Open` branch below only keeps `defaultFor()` a total function, matching the
 * mapping it inherited from `RegistrationSettings::defaultFor()`.
 *
 * Self-hosted household creation is deliberately NOT expressed here. Its
 * default (`allowed`) belongs to `RegistrationSettings::householdCreationPolicy`
 * because it describes a different axis than channel admission.
 */
final class SelfHostedRegistrationPolicyDefaults implements RegistrationPolicyDefaults
{
    /**
     * Resolve the secure self-hosted default for a channel.
     *
     * @param  RegistrationChannel  $channel  The channel to resolve.
     * @return RegistrationPolicy The fallback policy.
     */
    public function defaultFor(RegistrationChannel $channel): RegistrationPolicy
    {
        return match ($channel) {
            RegistrationChannel::Web, RegistrationChannel::Api => RegistrationPolicy::Disabled,
            RegistrationChannel::Google => RegistrationPolicy::ApprovalRequired,
            RegistrationChannel::HouseholdInvitation,
            RegistrationChannel::AdminInvitation => RegistrationPolicy::Open,
        };
    }
}
