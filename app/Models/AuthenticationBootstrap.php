<?php

namespace App\Models;

use App\Enums\AuthenticationBootstrapPurpose;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class AuthenticationBootstrap
 *
 * A single-use, short-lived grant that lets a browser start (or recover) a
 * two-factor enrolment without a session. It replaces the old signed
 * `GET /otp/setup/{user}` URL: a signed URL is a bearer credential tied to a
 * user id, it cannot be consumed atomically, and it lives in browser history,
 * referrers and mail clients. This row is the opposite: the token is random,
 * stored only as a keyed digest, bound to a purpose, expires quickly and is
 * consumed by an atomic conditional update.
 *
 * The table never stores the raw token or the TOTP secret. `token_hash` is the
 * keyed digest used for lookup; the plaintext token is returned exactly once,
 * by the repository that issues it. The model also hides the digest from any
 * accidental array/JSON serialisation.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the bootstrap (UUID).
 * @property string $user_id The user the grant was issued for.
 * @property string $token_hash The keyed digest of the token, never the token.
 * @property AuthenticationBootstrapPurpose $purpose The grant's deliberate purpose.
 * @property Carbon $expires_at When the grant stops being redeemable.
 * @property Carbon|null $consumed_at When the grant was redeemed, null while active.
 * @property Carbon|null $created_at The timestamp when the grant was issued.
 * @property Carbon|null $updated_at The timestamp when the grant was last touched.
 *
 * Relationships:
 * @property User|null $user The user the grant authorises.
 *
 * @mixin Model
 */
class AuthenticationBootstrap extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'token_hash',
        'purpose',
        'expires_at',
        'consumed_at',
    ];

    /**
     * The digest is a lookup key, never a response payload.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => AuthenticationBootstrapPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the grant is still redeemable.
     *
     * @return bool True when it was neither consumed nor expired.
     */
    public function isActive(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at instanceof Carbon
            && $this->expires_at->isFuture();
    }

    /**
     * Determine whether completing this grant may establish a session.
     *
     * The enrolment (or recovery) itself is allowed even when this returns
     * false — a pending, unverified or suspended user finishes setup and is
     * simply not signed in. The rule is deliberately a property of the grant
     * rather than of the transport, so every adapter (web, API) asks the same
     * domain question.
     *
     * @return bool True when approval, account status and email verification all allow a session.
     */
    public function mayEstablishSession(): bool
    {
        $user = $this->user;

        return $user !== null
            && $user->hasVerifiedEmail()
            && $user->canAccessApplication();
    }

    /**
     * Restrict a query to redeemable grants.
     *
     * @param  Builder<AuthenticationBootstrap>  $query  The query to constrain.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }
}
