<?php

namespace App\Support\Auth;

/**
 * Single source of truth for the Google sign-in second factor policy.
 *
 * A Google ID token asserts the identity of the person signing in, but it says
 * nothing about whether that Google account is protected by MFA. Delegating the
 * second factor therefore means trusting the provider's policy instead of
 * enforcing one locally. The default is delegated; the operator can require the
 * application's own TOTP by enabling `auth.google.require_otp`, which restores
 * the previous behaviour byte for byte.
 *
 * Every transport (web callback, JSON API, Google registration) asks this class,
 * so the three decisions can never drift apart.
 */
final class GoogleSecondFactorPolicy
{
    /**
     * Determine whether a Google sign-in must still present the application OTP.
     *
     * @return bool True when the operator requires the local second factor.
     */
    public static function requiresApplicationOtp(): bool
    {
        return (bool) config('auth.google.require_otp', false);
    }

    /**
     * Determine whether the second factor is delegated to Google.
     *
     * @return bool True when no application OTP is required after Google sign-in.
     */
    public static function delegates(): bool
    {
        return ! self::requiresApplicationOtp();
    }
}
