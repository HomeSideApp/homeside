<?php

namespace App\Data\Auth;

use App\Enums\RegistrationDecision;
use App\Models\User;

/**
 * The typed result of running the shared registration use case.
 *
 * It carries the domain decision and the next authentication step, never an
 * HTTP status, a redirect or a token: each transport adapter maps the outcome
 * onto its own response. `sessionAllowed` is a distinct, deliberate flag rather
 * than a derivation at the call site, so "the account exists" and "the account
 * may be signed in right now" can never be conflated.
 */
final readonly class RegistrationOutcomeData
{
    /**
     * The registration created or completed an account that needs no further
     * administrative decision but still has to verify its email.
     */
    public const NEXT_VERIFY_EMAIL = 'verify_email';

    /** The registration is waiting for an administrator to approve it. */
    public const NEXT_AWAIT_APPROVAL = 'await_approval';

    /** The registration is complete; the account must enrol two-factor next. */
    public const NEXT_ENROL_TWO_FACTOR = 'enrol_two_factor';

    /** The account is fully ready; a session may be established. */
    public const NEXT_AUTHENTICATED = 'authenticated';

    /** The caller must authenticate as the existing account; nothing was created. */
    public const NEXT_AUTHENTICATE = 'authenticate';

    /** No further step is possible (denied or blocked). */
    public const NEXT_NONE = 'none';

    /**
     * @param  User|null  $user  The created, completed or matched user, when one exists.
     * @param  RegistrationDecision  $decision  The domain decision that was reached.
     * @param  string  $nextStep  One of the NEXT_* constants.
     * @param  bool  $sessionAllowed  Whether the transport may establish a session.
     * @param  string|null  $reason  An optional stable machine code.
     */
    public function __construct(
        public ?User $user,
        public RegistrationDecision $decision,
        public string $nextStep,
        public bool $sessionAllowed,
        public ?string $reason = null,
    ) {}

    /**
     * Determine whether an account exists as a result of the registration.
     *
     * @return bool True when a user is attached to the outcome.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Determine whether the registration created or completed the account.
     *
     * An `existing_identity` or `link_required` outcome matches a user but never
     * changes it, so it is deliberately not a success.
     *
     * @return bool True for the allowed and approval-required decisions.
     */
    public function registered(): bool
    {
        return in_array($this->decision, [RegistrationDecision::Allowed, RegistrationDecision::ApprovalRequired], true);
    }
}
