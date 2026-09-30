<?php

namespace App\Enums;

/**
 * Lifecycle states of a household invitation.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * Get the human-readable label for the status.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }
}
