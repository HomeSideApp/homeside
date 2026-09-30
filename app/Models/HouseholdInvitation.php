<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class HouseholdInvitation
 *
 * This class represents an invitation for a user to join a household.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the invitation (UUID).
 * @property string $household_id The id of the household the invitation belongs to.
 * @property string $invited_by The id of the user who sent the invitation.
 * @property string|null $email The email address of the invited user, if any.
 * @property string|null $contact_id The inviter's contact linked to the invitation, if any.
 * @property InvitationStatus $status The status of the invitation.
 * @property string $token The unique token used to accept the invitation.
 * @property Carbon $expires_at The timestamp when the invitation expires.
 * @property Carbon|null $accepted_at The timestamp when the invitation was accepted, if any.
 * @property Carbon|null $created_at The timestamp when the invitation was created.
 * @property Carbon|null $updated_at The timestamp when the invitation was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the invitation belongs to.
 * @property User|null $inviter The user who sent the invitation.
 * @property Contact|null $contact The inviter's contact matched or created for the invitation.
 *
 * @mixin Model
 */
class HouseholdInvitation extends Model
{
    use HasUuids;

    protected $fillable = ['household_id', 'invited_by', 'email', 'contact_id', 'status', 'token', 'expires_at', 'accepted_at'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<HouseholdInvitation>  $query
     * @return Builder<HouseholdInvitation>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now());
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Determine if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Determine if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }
}
