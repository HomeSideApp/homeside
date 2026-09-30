<?php

namespace App\Enums;

/**
 * How an economic transaction is split between participants.
 */
enum SplitType: string
{
    case Equal = 'equal';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
