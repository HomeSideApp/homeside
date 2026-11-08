<?php

namespace App\Services\Auth;

use App\Contracts\RegistrationGate;
use App\Data\Registration\RegistrationDecisionData;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\User;

/**
 * The self-hosted registration gate.
 *
 * It answers exactly one question: may this identity start a registration on
 * this channel? It reads the channel policy and maps it to a typed decision.
 * It never touches HTTP, sessions, tokens or household membership, and it never
 * decides household-creation rights (those belong to EntitlementGate).
 *
 * Invitation channels bypass the policy entirely: a household or administrator
 * invitation is valid or invalid on its own terms (token, expiry, remaining
 * uses), so the gate always allows the registration and the invitation flow
 * enforces its own rules.
 */
final class SelfHostedRegistrationGate implements RegistrationGate
{
    public function __construct(private RegistrationSettings $settings) {}

    /**
     * Decide whether the identity may start a registration on the channel.
     *
     * @param  RegistrationChannel  $channel  The channel requesting the registration.
     * @param  string|null  $email  The normalised identity, used to resolve `existing_only`.
     * @return RegistrationDecisionData The typed decision.
     */
    public function evaluate(RegistrationChannel $channel, ?string $email = null): RegistrationDecisionData
    {
        if ($channel->isInvitation()) {
            return new RegistrationDecisionData(
                decision: RegistrationDecision::Allowed,
                channel: $channel,
                policy: RegistrationPolicy::Open,
                requiresApproval: false,
                requiresEmailVerification: false,
                reason: 'invitation_channel',
            );
        }

        $policy = $this->settings->policyFor($channel);

        return match ($policy) {
            RegistrationPolicy::Open => new RegistrationDecisionData(
                decision: RegistrationDecision::Allowed,
                channel: $channel,
                policy: $policy,
                requiresApproval: false,
                requiresEmailVerification: true,
            ),
            RegistrationPolicy::ApprovalRequired => new RegistrationDecisionData(
                decision: RegistrationDecision::ApprovalRequired,
                channel: $channel,
                policy: $policy,
                requiresApproval: true,
                requiresEmailVerification: true,
            ),
            RegistrationPolicy::Disabled => $this->denied($channel, $policy, 'registration_disabled'),
            RegistrationPolicy::ExistingOnly => $this->existingOnly($channel, $policy, $email),
        };
    }

    /**
     * Resolve `existing_only`: known identities may proceed, new ones may not.
     *
     * @param  RegistrationChannel  $channel  The channel requesting the registration.
     * @param  RegistrationPolicy  $policy  The policy in effect.
     * @param  string|null  $email  The normalised identity.
     * @return RegistrationDecisionData The typed decision.
     */
    private function existingOnly(RegistrationChannel $channel, RegistrationPolicy $policy, ?string $email): RegistrationDecisionData
    {
        if ($email !== null && $email !== '' && User::query()->where('email', $email)->exists()) {
            return new RegistrationDecisionData(
                decision: RegistrationDecision::ExistingIdentity,
                channel: $channel,
                policy: $policy,
                requiresApproval: false,
                requiresEmailVerification: false,
                reason: 'identity_exists',
            );
        }

        return $this->denied($channel, $policy, 'registration_existing_only');
    }

    /**
     * Build the denied decision.
     *
     * @param  RegistrationChannel  $channel  The channel that was denied.
     * @param  RegistrationPolicy  $policy  The policy that denied it.
     * @param  string  $reason  The stable machine code.
     * @return RegistrationDecisionData The typed decision.
     */
    private function denied(RegistrationChannel $channel, RegistrationPolicy $policy, string $reason): RegistrationDecisionData
    {
        return new RegistrationDecisionData(
            decision: RegistrationDecision::Denied,
            channel: $channel,
            policy: $policy,
            requiresApproval: false,
            requiresEmailVerification: false,
            reason: $reason,
        );
    }
}
