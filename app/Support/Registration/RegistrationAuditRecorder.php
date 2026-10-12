<?php

namespace App\Support\Registration;

use App\Enums\RegistrationAuditAction;
use App\Models\RegistrationAudit;
use App\Models\User;

/**
 * Single writer for the registration audit trail.
 *
 * Every later phase (approval, rejection, suspension, deletion, block release)
 * records its transition through `append`, so the table has one consistent
 * shape and one place to enforce the "no secrets" rule.
 *
 * NEVER pass tokens, verification links, TOTP secrets, recovery codes,
 * passwords or any other reusable credential: `invitation_reference` is an
 * opaque public identifier, not a token.
 */
final class RegistrationAuditRecorder
{
    /**
     * Append one transition to the audit trail.
     *
     * @param  RegistrationAuditAction  $action  The transition that happened.
     * @param  User|null  $user  The subject user, when one exists.
     * @param  User|null  $actor  The administrator or system actor, when known.
     * @param  string|null  $previousStatus  The status value before the action.
     * @param  string|null  $newStatus  The status value after the action.
     * @param  string|null  $channel  The registration channel involved.
     * @param  string|null  $origin  The transport origin, e.g. `web` or `api`.
     * @param  string|null  $invitationReference  The public invitation identifier, never a token.
     * @return RegistrationAudit The appended entry.
     */
    public function append(
        RegistrationAuditAction $action,
        ?User $user = null,
        ?User $actor = null,
        ?string $previousStatus = null,
        ?string $newStatus = null,
        ?string $channel = null,
        ?string $origin = null,
        ?string $invitationReference = null,
    ): RegistrationAudit {
        return RegistrationAudit::create([
            'action' => $action,
            'user_id' => $user?->id,
            'actor_id' => $actor?->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'channel' => $channel,
            'origin' => $origin,
            'invitation_reference' => $invitationReference,
        ]);
    }
}
