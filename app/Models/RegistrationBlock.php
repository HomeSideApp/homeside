<?php

namespace App\Models;

use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class RegistrationBlock
 *
 * A persistent, identity-level denial of registration. The stored value is an
 * HMAC of the normalised identifier, never the identifier in clear text, so the
 * table cannot be turned into a dictionary attack against known emails. Blocks
 * survive the deletion of the associated user row: `created_by`/`released_by`
 * are historical references and become null when the actor is deleted.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the block (UUID).
 * @property string $identifier_type The identity namespace, e.g. `email`.
 * @property string $identifier_hash The HMAC-SHA256 of the normalised identifier.
 * @property string|null $active_identifier_hash Mirror of the hash while active, null once released.
 * @property string|null $identifier_key_id The key id that produced the hash, for future rotation.
 * @property RegistrationBlockReason $reason_code Why the identity was blocked.
 * @property string|null $reason_note Optional human note.
 * @property RegistrationBlockSource $source Whether a human or the system created it.
 * @property string|null $created_by The administrator who created the block.
 * @property string|null $released_by The administrator who released the block.
 * @property Carbon|null $released_at When the block was released; null while active.
 * @property Carbon|null $created_at The timestamp when the block was created.
 * @property Carbon|null $updated_at The timestamp when the block was last updated.
 *
 * Relationships:
 * @property User|null $creator The administrator who created the block.
 * @property User|null $releaser The administrator who released the block.
 *
 * @mixin Model
 */
class RegistrationBlock extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'identifier_type',
        'identifier_hash',
        'active_identifier_hash',
        'identifier_key_id',
        'reason_code',
        'reason_note',
        'source',
        'created_by',
        'released_by',
        'released_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason_code' => RegistrationBlockReason::class,
            'source' => RegistrationBlockSource::class,
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the uniqueness mirror in step with `released_at` on every save.
        // Bulk updates bypass this hook, so the repository also sets the mirror
        // explicitly when it releases a block.
        static::saving(function (self $block): void {
            $block->active_identifier_hash = $block->released_at === null
                ? $block->identifier_hash
                : null;
        });
    }

    /**
     * Determine whether the block is still active.
     *
     * @return bool True when the block refuses the identity.
     */
    public function isActive(): bool
    {
        return $this->released_at === null;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** @param Builder<RegistrationBlock> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('released_at');
    }
}
