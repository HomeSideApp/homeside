<?php

namespace App\Data\Auth;

/**
 * The invitation context an invitation-channel registration is authorised by.
 *
 * `reference` is an OPAQUE PUBLIC IDENTIFIER (a row id), never the invitation
 * token: the audit trail must be able to name the invitation without storing a
 * reusable credential.
 *
 * `targetUserId` is set only for a TARGETED invitation: an account an
 * administrator already created and invited by email. Possession of the signed
 * invitation link proves control of that address, so the acceptance completes
 * the pre-created account instead of creating a new one. It must never be used
 * to complete an account the caller cannot prove ownership of.
 */
final readonly class InvitationContextData
{
    /**
     * @param  string|null  $reference  The opaque public invitation identifier, never a token.
     * @param  string|null  $targetUserId  The pre-created user for a targeted invitation, if any.
     * @param  bool  $emailVerified  Whether acceptance proves possession of the address.
     * @param  bool  $targeted  Whether the invitation names a single address.
     */
    public function __construct(
        public ?string $reference = null,
        public ?string $targetUserId = null,
        public bool $emailVerified = false,
        public bool $targeted = false,
    ) {}

    /**
     * Determine whether the context completes a pre-created account.
     *
     * @return bool True when a specific, administrator-created user is the target.
     */
    public function completesExistingAccount(): bool
    {
        return $this->targetUserId !== null;
    }
}
