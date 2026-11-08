<?php

namespace App\Enums;

/**
 * Administrative approval state of a user account.
 *
 * `not_required` and `approved` are deliberately distinct even though both
 * allow access. A user whose channel policy never demanded approval carries
 * `not_required`; `approved` records an actual administrative decision. Keeping
 * them apart makes an intervention provable instead of indistinguishable from
 * "no decision was needed", which matters for auditing who let a person in.
 */
enum ApprovalStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Get the human-readable label for the status.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
