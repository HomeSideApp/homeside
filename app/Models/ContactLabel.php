<?php

namespace App\Models;

use Database\Factories\ContactLabelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactLabel extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $label): void {
            if (($label->user_id === null) === ($label->household_id === null)) {
                throw new \InvalidArgumentException('A label must belong to exactly one user or household.');
            }
        });
    }

    /** @use HasFactory<ContactLabelFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'household_id', 'name'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id)
                ->orWhereIn('household_id', $user->households()->select('households.id'));
        });
    }

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_contact_label')->withPivot('manual', 'imported');
    }
}
