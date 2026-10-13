<?php

namespace App\Enums;

/**
 * The deliberate, single-use grant a bootstrap token stands for.
 *
 * The purposes are distinct on purpose. A token is hashed with its purpose as a
 * domain separator, so a grant issued to set up a second factor can never be
 * redeemed as an account-recovery grant and vice versa. Two flows that both end
 * in a session must not be interchangeable at the credential level: keeping
 * them apart is what stops a recovery link from being replayed as an enrolment
 * link (and the reverse).
 */
enum AuthenticationBootstrapPurpose: string
{
    case TwoFactorEnrolment = 'two_factor_enrolment';
    case AccountRecovery = 'account_recovery';

    /**
     * Get the human-readable label for the purpose.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::TwoFactorEnrolment => 'Two-factor enrolment',
            self::AccountRecovery => 'Account recovery',
        };
    }
}
