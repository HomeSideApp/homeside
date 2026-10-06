<?php

namespace App\Models;

use App\Enums\HouseholdAccessProfile;
use App\Enums\HouseholdRole;
use App\Support\HouseholdAccess;
use Database\Factories\HouseholdMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class HouseholdMember
 *
 * This class represents a user's membership of a household, including their role within it.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the membership (UUID).
 * @property string $household_id The id of the household the membership belongs to.
 * @property string $user_id The id of the user the membership belongs to.
 * @property HouseholdAccessProfile|null $access_profile
 * @property array<string, string>|null $module_access
 * @property HouseholdRole $role The role of the user within the household.
 * @property Carbon|null $joined_at The timestamp when the user joined the household, if any.
 * @property string|null $contact_id The contact linked to this member, if any.
 * @property Carbon|null $created_at The timestamp when the membership was created.
 * @property Carbon|null $updated_at The timestamp when the membership was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the membership belongs to.
 * @property User|null $user The user the membership belongs to.
 * @property Contact|null $contact The contact linked to this member, if any.
 *
 * @mixin Model
 */
class HouseholdMember extends Model
{
    /** @use HasFactory<HouseholdMemberFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['household_id', 'user_id', 'role', 'joined_at', 'contact_id', 'access_profile', 'module_access'];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            $profile = $member->access_profile ?? ($member->role === HouseholdRole::Admin ? HouseholdAccessProfile::Admin : HouseholdAccessProfile::Member);
            $member->access_profile = $profile;
            $member->role = $profile->role();
            $member->module_access = array_replace($profile->permissions(), $member->module_access ?? []);
        });
        static::saved(fn (self $member) => app(HouseholdAccess::class)->forget($member->user_id));
        static::deleted(fn (self $member) => app(HouseholdAccess::class)->forget($member->user_id));
    }

    /** @return array<string, string> */
    public function permissions(): array
    {
        $profile = $this->access_profile ?? ($this->role === HouseholdRole::Admin ? HouseholdAccessProfile::Admin : HouseholdAccessProfile::Member);

        return array_replace($profile->permissions(), $this->module_access ?? []);
    }

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => HouseholdRole::class,
            'access_profile' => HouseholdAccessProfile::class,
            'module_access' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
