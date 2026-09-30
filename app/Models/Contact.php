<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            if (($contact->user_id === null) === ($contact->household_id === null)) {
                throw new \InvalidArgumentException('A contact must belong to exactly one user or household.');
            }
        });
    }

    protected $fillable = ['user_id', 'household_id', 'type', 'display_name', 'preferred_record_id', 'preferred_photo_record_id'];

    /** @return HasMany<ContactRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ContactRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsToMany<ContactLabel, $this> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(ContactLabel::class, 'contact_contact_label')->withPivot('manual', 'imported');
    }

    /** @return BelongsToMany<EconomicTransaction, $this> */
    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(EconomicTransaction::class, 'economic_transaction_contacts')->withPivot('role');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('household_id', $user->households()->select('households.id'));
        });
    }
}
