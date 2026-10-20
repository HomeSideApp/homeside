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

    /**
     * Resolve how long a grant for this purpose stays redeemable, in minutes.
     *
     * The two lifetimes differ on purpose:
     *
     *  - enrolment (12 hours by default) is a step a person completes between
     *    other things: they must read the mail, install an authenticator and
     *    come back. A 30 minute window turns a slow afternoon into a dead end;
     *    the grant only lets the holder set up a factor on an account they just
     *    proved they own, so the wider window does not hand out a session.
     *  - recovery (30 minutes by default) can REPLACE an existing second factor,
     *    which is the difference between a late visitor and a stolen mailbox
     *    becoming a permanent takeover. The less time such a link lives, the
     *    better, so it keeps the short window.
     *
     * Both values are deployment configuration (`config/registration.php`).
     *
     * @return int The lifetime of a grant for this purpose, in minutes.
     */
    public function ttlMinutes(): int
    {
        $configured = config('registration.bootstrap.ttl_minutes.'.$this->value);
        $minutes = is_numeric($configured) ? (int) $configured : $this->shippedTtlMinutes();

        return $minutes > 0 ? $minutes : $this->shippedTtlMinutes();
    }

    /**
     * The default lifetime shipped with the application.
     *
     * It mirrors the defaults in `config/registration.php` and only applies when
     * that key is missing altogether (for example a stale compiled config).
     *
     * @return int The shipped lifetime, in minutes.
     */
    public function shippedTtlMinutes(): int
    {
        return match ($this) {
            self::TwoFactorEnrolment => 12 * 60,
            self::AccountRecovery => 30,
        };
    }
}
