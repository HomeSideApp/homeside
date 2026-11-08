<?php

namespace App\Data\Auth;

/**
 * A verified external identity (currently Google) presented for registration.
 *
 * The DTO carries the provider's own assertion of the email and its
 * verification claim. The claim is only trustworthy when the provider actually
 * returns a reliable `email_verified` assertion, so the transport is
 * responsible for populating the flag only in that case; the registration use
 * case trusts it as given and never re-derives it.
 */
final readonly class ExternalIdentityData
{
    /**
     * @param  string  $provider  The external provider key, e.g. `google`.
     * @param  string  $subject  The provider's stable subject identifier.
     * @param  string  $email  The email the provider asserts.
     * @param  bool  $emailVerified  Whether the provider reliably verified the email.
     */
    public function __construct(
        public string $provider,
        public string $subject,
        public string $email,
        public bool $emailVerified,
    ) {}

    /**
     * Get the normalised provider email.
     *
     * @return string The lowercased, trimmed address.
     */
    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->email));
    }
}
