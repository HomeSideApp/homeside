<?php

namespace App\Enums;

/**
 * The administrative condition of a user's identity.
 *
 * This axis is INDEPENDENT from ApprovalStatus. An account can be `active` and
 * still be `pending` approval, or `suspended`/`blocked` regardless of whether
 * it was ever approved. Keep the two separate: approval records "may this
 * identity exist on the platform?", while account status records "is this
 * identity currently allowed to operate?".
 *
 * A persistent RegistrationBlock is a stronger, identity-level version of
 * `blocked`: it survives user deletion and refuses re-registration, whereas
 * `blocked`/`suspended` describe the lifecycle of an existing account row.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';

    /**
     * Determine whether the status grants effective application access.
     *
     * Only `active` allows access. A suspended or blocked account must lose
     * access everywhere, not just at the login screen.
     *
     * @return bool True when the account may operate.
     */
    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get the human-readable label for the status.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Blocked => 'Blocked',
        };
    }
}
