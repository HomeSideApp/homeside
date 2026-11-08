<?php

namespace App\Enums;

/**
 * Why an identity was blocked from registering or signing in again.
 *
 * The reason is a stable machine code, not free text: a `reason_note` column
 * carries the human detail separately so the code stays queryable and safe for
 * i18n. The list is deliberately explicit rather than an opaque string.
 */
enum RegistrationBlockReason: string
{
    case RejectedRegistration = 'rejected_registration';
    case DeletedAndBlocked = 'deleted_and_blocked';
    case ManualBlock = 'manual_block';
    case Abuse = 'abuse';
    case SecurityRisk = 'security_risk';
    case Other = 'other';

    /**
     * Get the human-readable label for the reason.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::RejectedRegistration => 'Rejected registration',
            self::DeletedAndBlocked => 'Deleted and blocked',
            self::ManualBlock => 'Manual block',
            self::Abuse => 'Abuse',
            self::SecurityRisk => 'Security risk',
            self::Other => 'Other',
        };
    }
}
