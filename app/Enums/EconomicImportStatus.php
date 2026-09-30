<?php

namespace App\Enums;

/**
 * Lifecycle states of an economic import.
 */
enum EconomicImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case ReadyForReview = 'ready_for_review';
    case Failed = 'failed';
    case Confirmed = 'confirmed';
    case Discarded = 'discarded';
}
