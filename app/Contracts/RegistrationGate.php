<?php

namespace App\Contracts;

use App\Data\Registration\RegistrationDecisionData;
use App\Enums\RegistrationChannel;

/**
 * Contract for the app's registration gate.
 *
 * The gate answers exactly one question: may this identity start a new
 * registration on this channel? It is a pure domain port. It returns a typed
 * decision and never a boolean, HTTP response, redirect, Inertia component or
 * side effect, so the transport layer owns every status code and body shape.
 *
 * The gate deliberately says nothing about what a member may do after joining.
 * Household creation, capacities and entitlements belong to EntitlementGate;
 * the gate only decides whether a registration may begin.
 */
interface RegistrationGate
{
    /**
     * Decide whether the identity may start a registration on the channel.
     *
     * @param  RegistrationChannel  $channel  The channel requesting the registration.
     * @param  string|null  $email  The normalised identity when the caller already knows it, used to resolve `existing_only`.
     * @return RegistrationDecisionData The typed decision.
     */
    public function evaluate(RegistrationChannel $channel, ?string $email = null): RegistrationDecisionData;
}
