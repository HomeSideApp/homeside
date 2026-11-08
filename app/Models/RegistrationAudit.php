<?php

namespace App\Models;

use App\Enums\RegistrationAuditAction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class RegistrationAudit
 *
 * An append-only record of a registration or account-lifecycle transition. The
 * table never stores tokens, secrets, passwords or challenges: only the action,
 * the parties, the statuses before and after, and the channel/origin metadata.
 * `user_id` and `actor_id` are nullable and null-on-delete so the history
 * outlives the rows it describes.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the entry (UUID).
 * @property string|null $user_id The subject user, or null once deleted.
 * @property RegistrationAuditAction $action The transition that happened.
 * @property string|null $actor_id The administrator or system actor.
 * @property string|null $previous_status The status value before the action.
 * @property string|null $new_status The status value after the action.
 * @property string|null $channel The registration channel involved.
 * @property string|null $origin The transport origin, e.g. `web` or `api`.
 * @property string|null $invitation_reference The invitation identifier, when relevant.
 * @property Carbon|null $created_at The timestamp when the entry was written.
 *
 * Relationships:
 * @property User|null $user The subject user.
 * @property User|null $actor The administrator who acted.
 *
 * @mixin Model
 */
class RegistrationAudit extends Model
{
    use HasUuids;

    /**
     * The audit log is append-only: it never has an updated_at.
     */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'actor_id',
        'previous_status',
        'new_status',
        'channel',
        'origin',
        'invitation_reference',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => RegistrationAuditAction::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
