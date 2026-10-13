<?php

namespace App\Support\Auth;

use App\Contracts\AuthenticationBootstrapRepository as AuthenticationBootstrapRepositoryContract;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use RuntimeException;

/**
 * Eloquent-backed single-use authentication bootstrap repository.
 *
 * Token generation and storage
 * ----------------------------
 * The token is 32 CSPRNG bytes (256 bits) from `random_bytes`, base64url
 * encoded so it is URL-safe, and stored ONLY as an HMAC-SHA256 digest keyed
 * with the dedicated registration key (`config('registration.block.key')`).
 *
 * Why a keyed digest and not a plain `sha256`? A plain hash of a high-entropy
 * random token is defensible — unlike an email address, the token is not
 * guessable, so a stolen table cannot be dictionary-attacked. We still choose
 * the HMAC, because it reuses the exact construction and key discipline as the
 * P2 identity blocks: one story for "we hash secrets we never want to be
 * reversible", and a stolen database is useless without the key. The digest is
 * domain-separated with a bootstrap namespace AND the purpose, so the same
 * token value can never match across purposes and a recovery grant cannot be
 * replayed as an enrolment grant. The raw token is never normalised (base64 is
 * case-sensitive): only the message is prefixed, never the secret itself.
 *
 * Atomicity
 * ---------
 * `consume()` is a conditional UPDATE on `consumed_at IS NULL` + unexpired
 * `expires_at`. The database row is the arbiter: whichever concurrent request
 * updates the row first wins and every other one observes an affected-row count
 * of zero, so a token is redeemable at most once even under a race.
 */
final class AuthenticationBootstrapRepository implements AuthenticationBootstrapRepositoryContract
{
    /**
     * The number of CSPRNG bytes behind every token (256 bits of entropy).
     */
    public const TOKEN_BYTES = 32;

    /**
     * The HMAC domain separator, keeping bootstrap digests out of every other
     * keyed-hash namespace that shares the registration block key.
     */
    private const DOMAIN = 'authentication-bootstrap';

    /**
     * {@inheritDoc}
     */
    public function issue(User $user, AuthenticationBootstrapPurpose $purpose, int $ttlMinutes = 30): string
    {
        $token = $this->newToken();

        AuthenticationBootstrap::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => $this->hash($purpose, $token),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return $token;
    }

    /**
     * {@inheritDoc}
     */
    public function consume(string $rawToken, AuthenticationBootstrapPurpose $purpose): ?AuthenticationBootstrap
    {
        $hash = $this->hash($purpose, $rawToken);

        $affected = AuthenticationBootstrap::query()
            ->where('token_hash', $hash)
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        if ($affected === 0) {
            return null;
        }

        return AuthenticationBootstrap::query()
            ->where('token_hash', $hash)
            ->where('purpose', $purpose->value)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findActive(string $rawToken, AuthenticationBootstrapPurpose $purpose): ?AuthenticationBootstrap
    {
        return AuthenticationBootstrap::query()
            ->active()
            ->where('token_hash', $this->hash($purpose, $rawToken))
            ->where('purpose', $purpose->value)
            ->first();
    }

    /**
     * {@inheritDoc}
     */
    public function invalidateFor(User $user, AuthenticationBootstrapPurpose $purpose): void
    {
        AuthenticationBootstrap::query()
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose->value)
            ->whereNull('consumed_at')
            ->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function invalidateAllFor(User $user): void
    {
        AuthenticationBootstrap::query()
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->delete();
    }

    /**
     * Generate a fresh, URL-safe, high-entropy token.
     *
     * @return string The base64url-encoded token.
     */
    private function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
    }

    /**
     * Compute the keyed digest stored for a token.
     *
     * @param  AuthenticationBootstrapPurpose  $purpose  The domain separator for the grant.
     * @param  string  $rawToken  The plaintext token.
     * @return string The lowercase hexadecimal HMAC.
     *
     * @throws RuntimeException When the dedicated key is not configured.
     */
    private function hash(AuthenticationBootstrapPurpose $purpose, string $rawToken): string
    {
        return hash_hmac(
            'sha256',
            self::DOMAIN."\0".$purpose->value."\0".$rawToken,
            $this->key(),
        );
    }

    /**
     * Resolve and validate the dedicated HMAC key.
     *
     * Fails closed: a missing key is a configuration error, never a reason to
     * fall back to an unkeyed hash.
     *
     * @return string The configured key.
     *
     * @throws RuntimeException When the key is missing or empty.
     */
    private function key(): string
    {
        $key = config('registration.block.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException(
                'REGISTRATION_BLOCK_KEY is not configured. Authentication bootstrap tokens '
                .'share the dedicated registration key; an unkeyed hash is not an acceptable fallback.'
            );
        }

        return $key;
    }
}
