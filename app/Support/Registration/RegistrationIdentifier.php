<?php

namespace App\Support\Registration;

use RuntimeException;

/**
 * Normalises an identity and computes the HMAC used to store and match it.
 *
 * The stored hash is NEVER a plain hash of the identifier. `sha256($email)`
 * enables a dictionary attack: anyone who obtains the table can confirm
 * whether a known address was blocked by hashing the guess. An HMAC keyed with
 * a dedicated, secret application key makes that impossible without the key.
 *
 * The message is domain-separated by identifier type, so the same raw value
 * registered as two different identity types produces two different hashes and
 * cannot be replayed across namespaces.
 *
 * Key lifecycle: the key comes from `config('registration.block.key')`
 * (`REGISTRATION_BLOCK_KEY`). It is deliberately NOT the app key, so rotating
 * APP_KEY does not invalidate stored blocks. Rotating the block key requires
 * reindexing all existing rows with the old key, using `identifier_key_id` to
 * know which key produced each hash; until the reindex completes, every active
 * key id must be checked. A lost key makes existing blocks unmatchable.
 */
final class RegistrationIdentifier
{
    /**
     * The identity type used for email addresses.
     */
    public const EMAIL = 'email';

    /**
     * Normalise a raw identifier to its canonical lookup form.
     *
     * Email addresses are trimmed and case-folded, which is the minimum the
     * registration flow guarantees. Other identity types are trimmed and
     * case-folded too; no type is case-sensitive today.
     *
     * @param  string  $rawIdentifier  The value as supplied by the caller.
     * @return string The canonical form.
     */
    public function normalize(string $rawIdentifier): string
    {
        return mb_strtolower(trim($rawIdentifier));
    }

    /**
     * Compute the HMAC-SHA256 for a normalised identifier.
     *
     * @param  string  $identifierType  The identity namespace, e.g. `email`.
     * @param  string  $rawIdentifier  The value as supplied by the caller.
     * @return string The lowercase hexadecimal HMAC.
     *
     * @throws RuntimeException When the dedicated block key is not configured.
     */
    public function hash(string $identifierType, string $rawIdentifier): string
    {
        return hash_hmac(
            $this->algorithm(),
            $identifierType."\0".$this->normalize($rawIdentifier),
            $this->key(),
        );
    }

    /**
     * Resolve the key id recorded with a hash, so a rotation can be reasoned about.
     *
     * @return string The configured key id.
     */
    public function keyId(): string
    {
        $keyId = config('registration.block.key_id', 'v1');

        return is_string($keyId) && $keyId !== '' ? $keyId : 'v1';
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
                'REGISTRATION_BLOCK_KEY is not configured. Set a dedicated, secret '
                .'registration block key; plain hashing is not an acceptable fallback.'
            );
        }

        return $key;
    }

    /**
     * Resolve the configured hashing algorithm.
     *
     * @return string The algorithm name.
     */
    private function algorithm(): string
    {
        $algorithm = config('registration.block.algorithm', 'sha256');

        return is_string($algorithm) && $algorithm !== '' ? $algorithm : 'sha256';
    }
}
