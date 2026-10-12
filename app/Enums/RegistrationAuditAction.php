<?php

namespace App\Enums;

/**
 * Every state transition the registration and account lifecycle can record.
 *
 * The set is explicit and extensible: adding a case is the only change needed
 * for a new consumer, and the string value is the stable contract persisted in
 * `registration_audits.action`. Values describe an observed fact, never a
 * secret; tokens, challenges and passwords must never reach the audit table.
 */
enum RegistrationAuditAction: string
{
    case RegistrationCreated = 'registration_created';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RejectionReleased = 'rejection_released';
    case Suspended = 'suspended';
    case Unsuspended = 'unsuspended';
    case Blocked = 'blocked';
    case Unblocked = 'unblocked';
    case Deleted = 'deleted';
    case DeletedAndBlocked = 'deleted_and_blocked';
    case BlockReleased = 'block_released';

    /**
     * Get the human-readable label for the action.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::RegistrationCreated => 'Registration created',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::RejectionReleased => 'Rejection released',
            self::Suspended => 'Suspended',
            self::Unsuspended => 'Unsuspended',
            self::Blocked => 'Blocked',
            self::Unblocked => 'Unblocked',
            self::Deleted => 'Deleted',
            self::DeletedAndBlocked => 'Deleted and blocked',
            self::BlockReleased => 'Block released',
        };
    }
}
