<?php

namespace App\Contracts;

use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\RegistrationBlock;
use App\Models\User;

/**
 * Persistent storage for identity-level registration blocks.
 *
 * Implementations normalise and HMAC the identifier through
 * App\Support\Registration\RegistrationIdentifier; callers always pass the raw
 * identifier and never hash anything themselves, so the key never leaks into
 * transport or domain code.
 *
 * All mutating operations are idempotent and concurrency-safe:
 *  - `block` returns the existing active block instead of creating a second one;
 *  - two simultaneous releases both leave a single, released row;
 *  - a unique constraint backing `block` makes two concurrent calls collapse
 *    into one active row even under a race.
 */
interface RegistrationBlockRepository
{
    /**
     * Determine whether an identifier is currently blocked.
     *
     * A released block does not block: only an active row counts.
     *
     * @param  string  $identifierType  The identity namespace, e.g. `email`.
     * @param  string  $rawIdentifier  The unhashed identifier.
     * @return bool True when an active block exists.
     */
    public function isBlocked(string $identifierType, string $rawIdentifier): bool;

    /**
     * Return the active block for an identifier, if any.
     *
     * @param  string  $identifierType  The identity namespace, e.g. `email`.
     * @param  string  $rawIdentifier  The unhashed identifier.
     * @return RegistrationBlock|null The active block, or null.
     */
    public function activeBlockFor(string $identifierType, string $rawIdentifier): ?RegistrationBlock;

    /**
     * Create a block, idempotently.
     *
     * When an active block already exists for the identity, it is returned
     * unchanged; no duplicate row is produced.
     *
     * @param  string  $identifierType  The identity namespace, e.g. `email`.
     * @param  string  $rawIdentifier  The unhashed identifier.
     * @param  RegistrationBlockReason  $reason  Why the identity is blocked.
     * @param  RegistrationBlockSource  $source  Whether a human or the system acted.
     * @param  User|null  $actor  The administrator creating the block, if any.
     * @param  string|null  $note  Optional human-readable note.
     * @return RegistrationBlock The active block.
     */
    public function block(
        string $identifierType,
        string $rawIdentifier,
        RegistrationBlockReason $reason,
        RegistrationBlockSource $source,
        ?User $actor = null,
        ?string $note = null,
    ): RegistrationBlock;

    /**
     * Release a block, idempotently.
     *
     * Sets `released_by`/`released_at` and keeps the row as history.
     *
     * @param  RegistrationBlock  $block  The block to release.
     * @param  User|null  $actor  The administrator releasing it, if any.
     * @return RegistrationBlock The released block.
     */
    public function release(RegistrationBlock $block, ?User $actor = null): RegistrationBlock;

    /**
     * Release the active block for an identifier, if one exists.
     *
     * @param  string  $identifierType  The identity namespace, e.g. `email`.
     * @param  string  $rawIdentifier  The unhashed identifier.
     * @param  User|null  $actor  The administrator releasing it, if any.
     * @return RegistrationBlock|null The released block, or null when none was active.
     */
    public function releaseForIdentifier(string $identifierType, string $rawIdentifier, ?User $actor = null): ?RegistrationBlock;
}
