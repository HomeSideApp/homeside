<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class HouseholdInviteLink
 *
 * Reusable invitation link that lets people register into a household,
 * optionally limited by expiry date and maximum number of uses.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the link (UUID).
 * @property string $household_id The id of the household the link belongs to.
 * @property string $created_by The id of the user who created the link.
 * @property string $token The public token used to open the registration page.
 * @property Carbon|null $expires_at The timestamp when the link stops working, if limited.
 * @property int|null $max_uses The maximum number of registrations, if limited.
 * @property int $uses_count The number of registrations performed through the link.
 * @property Carbon|null $revoked_at The timestamp when the link was revoked, if any.
 * @property Carbon|null $created_at The timestamp when the link was created.
 * @property Carbon|null $updated_at The timestamp when the link was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the link belongs to.
 * @property User|null $creator The user who created the link.
 *
 * @mixin Model
 */
class HouseholdInviteLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'household_id',
        'created_by',
        'token',
        'expires_at',
        'max_uses',
        'uses_count',
        'revoked_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Determine whether the link still accepts new registrations.
     *
     * @return bool True when the link is active and within its limits
     */
    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }
}
