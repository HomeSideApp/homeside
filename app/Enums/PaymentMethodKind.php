<?php

namespace App\Enums;

/**
 * Class PaymentMethodKind
 *
 * Categorises a payment method so the user interface can group accounts and so the
 * crypto flow can be enabled only for crypto wallets.
 */
enum PaymentMethodKind: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case DigitalWallet = 'digital_wallet';
    case Crypto = 'crypto';
    case Cheque = 'cheque';
    case Other = 'other';

    /**
     * Determine whether methods of this kind hold crypto assets.
     *
     * @return bool True when the kind represents a crypto wallet; otherwise false.
     */
    public function isCrypto(): bool
    {
        return $this === self::Crypto;
    }
}
