<?php

namespace App\Enums;

/**
 * Supported frequencies for recurring economic transactions.
 */
enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
