<?php

namespace App\Contracts;

use App\Data\Registration\RegistrationDecisionData;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;

/**
 * Contract for the fallback registration policy of a policy-gated channel.
 *
 * The port answers exactly one question: which {@see RegistrationPolicy} governs
 * a channel when the installation has never stored a choice for it? It is the
 * seam that lets a different distribution (for example a commercial SaaS
 * package) start a fresh install with different admission defaults while the
 * decision logic — the mapping from a policy to a typed
 * {@see RegistrationDecisionData} — stays in exactly one
 * place: {@see RegistrationGate}.
 *
 * What it IS:
 *  - The value `RegistrationSettings::policyFor()` returns when the settings
 *    store holds no row for a policy-gated channel, or holds a value that is
 *    not a known policy. That is the whole surface.
 *
 * What it is explicitly NOT:
 *  - It is NOT an override of a persisted decision. A stored row always wins;
 *    the defaults are consulted only when there is nothing readable to honour.
 *    Changing this binding can never change the meaning of a setting an
 *    operator has already saved.
 *  - It is NOT a place to decide capacity, household-creation rights or
 *    authorisation. Capacities belong to
 *    {@see EntitlementGate}; household creation is a Policy plus
 *    an entitlement; permissions are Policies. None of that may leak in here.
 *  - It is NOT a place to name which policies a channel may hold. That list is
 *    `RegistrationSettings::allowedPoliciesFor()`, because it describes the
 *    vocabulary the settings surface exposes, not an installation default.
 *
 * Invitation channels (`HouseholdInvitation`, `AdminInvitation`) are not
 * policy-gated: `RegistrationSettings` resolves them through `InvitationPolicy`
 * (default `enabled`) and never consults this port for them. Implementations
 * still return a total answer for every {@see RegistrationChannel} so the
 * contract stays a pure function rather than an exception-throwing partial one;
 * that answer is inert because nothing routes an invitation through it.
 */
interface RegistrationPolicyDefaults
{
    /**
     * Resolve the fallback policy for a channel.
     *
     * @param  RegistrationChannel  $channel  The channel to resolve.
     * @return RegistrationPolicy The policy to use when no stored row exists.
     */
    public function defaultFor(RegistrationChannel $channel): RegistrationPolicy;
}
