<?php

namespace App\Enums;

/**
 * Visibility scope of an economic transaction within a household.
 */
enum TransactionScope: string
{
    /** Only visible to the creator of the transaction */
    case Personal = 'personal';

    /** Visible to all household members (supports participants) */
    case Shared = 'shared';
}
