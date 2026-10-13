<?php

namespace App\Data\Auth;

/**
 * The public, non-authorising description of how a client may register.
 *
 * This data answers "what does this installation offer right now?" so a mobile
 * or web client can build its interface. It is INFORMATION, not authorisation:
 * the API must re-evaluate the registration gate on every mutation, because a
 * capability read from this DTO can be stale the instant an administrator
 * changes the policy.
 */
final readonly class RegistrationCapabilitiesData
{
    /**
     * @param  array<string, array{available: bool, policy: string, approval_required: bool}>  $channels  The policy-gated channels, keyed by channel value.
     * @param  bool  $googleAvailable  Whether the Google channel can actually be started (policy and configuration).
     */
    public function __construct(
        public array $channels,
        public bool $googleAvailable,
    ) {}
}
