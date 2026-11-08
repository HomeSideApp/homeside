<?php

namespace App\Data\Registration;

use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;

/**
 * The typed outcome of asking the registration gate whether an identity may
 * start a registration on a channel.
 *
 * This is a domain value object: it never carries an HTTP status, redirect,
 * Inertia component or authentication token. Callers translate the decision
 * into their own transport.
 */
final readonly class RegistrationDecisionData
{
    /**
     * @param  RegistrationDecision  $decision  The decision the gate reached.
     * @param  RegistrationChannel  $channel  The channel that asked.
     * @param  RegistrationPolicy  $policy  The policy that produced the decision.
     * @param  bool  $requiresApproval  Whether an administrator must approve the registration.
     * @param  bool  $requiresEmailVerification  Whether the identity must verify its email.
     * @param  string|null  $reason  Optional stable machine code explaining the decision.
     */
    public function __construct(
        public RegistrationDecision $decision,
        public RegistrationChannel $channel,
        public RegistrationPolicy $policy,
        public bool $requiresApproval,
        public bool $requiresEmailVerification,
        public ?string $reason = null,
    ) {}

    /**
     * Determine whether the registration may proceed.
     *
     * @return bool True only for the `allowed` decision.
     */
    public function isAllowed(): bool
    {
        return $this->decision === RegistrationDecision::Allowed;
    }
}
