<?php

namespace App\Support\Registration;

use App\Contracts\RegistrationBlockRepository as RegistrationBlockRepositoryContract;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\RegistrationBlock;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Eloquent-backed registration block repository.
 *
 * Concurrency is handled at two levels: the unique constraint on
 * `(identifier_type, active_identifier_hash)` is the source of truth, and the
 * repository catches its violation to return the row a concurrent writer just
 * created. Releases are conditional updates, so two simultaneous releases end
 * in the same single released row.
 */
final class RegistrationBlockRepository implements RegistrationBlockRepositoryContract
{
    public function __construct(private RegistrationIdentifier $identifier) {}

    public function isBlocked(string $identifierType, string $rawIdentifier): bool
    {
        return $this->activeBlockFor($identifierType, $rawIdentifier) !== null;
    }

    public function activeBlockFor(string $identifierType, string $rawIdentifier): ?RegistrationBlock
    {
        $hash = $this->identifier->hash($identifierType, $rawIdentifier);

        return RegistrationBlock::query()
            ->active()
            ->where('identifier_type', $identifierType)
            ->where('identifier_hash', $hash)
            ->first();
    }

    public function block(
        string $identifierType,
        string $rawIdentifier,
        RegistrationBlockReason $reason,
        RegistrationBlockSource $source,
        ?User $actor = null,
        ?string $note = null,
    ): RegistrationBlock {
        $hash = $this->identifier->hash($identifierType, $rawIdentifier);

        return DB::transaction(function () use ($identifierType, $hash, $reason, $source, $actor, $note): RegistrationBlock {
            $existing = $this->activeRow($identifierType, $hash);

            if ($existing !== null) {
                return $existing;
            }

            try {
                return RegistrationBlock::create([
                    'identifier_type' => $identifierType,
                    'identifier_hash' => $hash,
                    'identifier_key_id' => $this->identifier->keyId(),
                    'reason_code' => $reason,
                    'reason_note' => $note,
                    'source' => $source,
                    'created_by' => $actor?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent writer won the race; its row is the active block.
                return $this->activeRow($identifierType, $hash) ?? throw new RuntimeException(
                    'Active registration block vanished immediately after a unique violation.'
                );
            }
        }, 3);
    }

    public function release(RegistrationBlock $block, ?User $actor = null): RegistrationBlock
    {
        if ($block->released_at !== null) {
            return $block;
        }

        // Conditional update: a second concurrent release matches no row and is
        // therefore idempotent instead of overwriting the first releaser.
        RegistrationBlock::query()
            ->whereKey($block->getKey())
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'released_by' => $actor?->id,
                'active_identifier_hash' => null,
                'updated_at' => now(),
            ]);

        return $block->refresh();
    }

    public function releaseForIdentifier(string $identifierType, string $rawIdentifier, ?User $actor = null): ?RegistrationBlock
    {
        $block = $this->activeBlockFor($identifierType, $rawIdentifier);

        return $block === null ? null : $this->release($block, $actor);
    }

    /**
     * Find the active row for an already-hashed identifier.
     *
     * @param  string  $identifierType  The identity namespace.
     * @param  string  $hash  The computed HMAC.
     * @return RegistrationBlock|null The active row, or null.
     */
    private function activeRow(string $identifierType, string $hash): ?RegistrationBlock
    {
        return RegistrationBlock::query()
            ->active()
            ->where('identifier_type', $identifierType)
            ->where('identifier_hash', $hash)
            ->first();
    }
}
