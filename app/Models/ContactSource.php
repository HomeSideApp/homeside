<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $encrypted_credentials
 * @property array<string, mixed>|null $encrypted_tokens
 * @property array<string, mixed>|null $provider_configuration
 * @property Carbon|null $token_expires_at
 */
class ContactSource extends Model
{
    public const DEFAULT_FAVORITE_LABEL = 'Starred';

    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $source): void {
            if (($source->user_id === null) === ($source->household_id === null)) {
                throw new \InvalidArgumentException('A contact source must belong to exactly one user or household.');
            }
        });
    }

    protected $fillable = ['user_id', 'household_id', 'provider', 'name', 'enabled', 'sync_enabled', 'authentication_type', 'encrypted_credentials', 'encrypted_tokens', 'token_expires_at', 'provider_configuration', 'last_sync_started_at', 'last_synced_at', 'last_sync_status', 'last_sync_error'];

    protected $hidden = ['encrypted_credentials', 'encrypted_tokens'];

    protected function casts(): array
    {
        return ['encrypted_credentials' => 'encrypted:array', 'encrypted_tokens' => 'encrypted:array', 'provider_configuration' => 'array', 'enabled' => 'boolean', 'sync_enabled' => 'boolean', 'token_expires_at' => 'datetime', 'last_synced_at' => 'datetime', 'last_sync_started_at' => 'datetime'];
    }

    public function favoriteLabel(): string
    {
        $label = $this->provider_configuration['favorite_label'] ?? null;

        return is_string($label) && trim($label) !== '' ? trim($label) : self::DEFAULT_FAVORITE_LABEL;
    }

    /** @return HasMany<ContactCollection, $this> */
    public function collections(): HasMany
    {
        return $this->hasMany(ContactCollection::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('household_id', $user->households()->select('households.id'));
        });
    }

    /** @return HasMany<ContactRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ContactRecord::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
