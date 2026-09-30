<?php

namespace App\Enums;

/**
 * Whether an economic transaction is an expense or an income.
 */
enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
}
