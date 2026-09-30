<?php

namespace App\Models;

use App\Enums\AppLocale;
use App\Notifications\InviteUserNotification;
use App\Traits\HasPermissionsTrait;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Class User
 *
 * This class represents a user in the application and extends the Authenticatable class.
 * It implements the MustVerifyEmail interface to support email verification.
 * The class uses several traits including HasApiTokens, HasFactory, HasPermissionsTrait, HasUuids,
 * and Notifiable.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the user (UUID).
 * @property string $name The name of the user.
 * @property string $email The email address of the user.
 * @property string $locale The user's preferred regional locale.
 * @property Carbon|null $email_verified_at The timestamp when the user's email was verified.
 * @property string $password The hashed password of the user.
 * @property string|null $remember_token The token used to remember the user.
 * @property string|null $active_household_id The id of the currently active household.
 * @property bool $households_enabled Whether household features are enabled for the user.
 * @property Carbon|null $created_at The timestamp when the user was created.
 * @property Carbon|null $updated_at The timestamp when the user was last updated.
 *
 * Relationships:
 * @property Household|null $activeHousehold The currently active household of the user.
 * @property Collection<int, Household> $households The households the user belongs to.
 *
 * @mixin Model
 */
#[Fillable(['name', 'email', 'locale', 'password', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'active_household_id', 'households_enabled', 'shares_personal_products', 'approval_status', 'approved_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPermissionsTrait, HasUuids, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'locale' => 'en-US',
        'approval_status' => 'approved',
        'households_enabled' => true,
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'households_enabled' => 'boolean',
            'shares_personal_products' => 'boolean',
        ];
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new InviteUserNotification);
    }

    public function preferredLocale(): string
    {
        return AppLocale::resolve($this->locale)->value;
    }

    /** @return BelongsToMany<Household, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members');
    }

    /** @return HasMany<HouseholdMember, $this> */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function activeHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'active_household_id');
    }

    /**
     * Determine if the user is an admin of the given household.
     */
    public function isAdminOf(Household $household): bool
    {
        return $household->isAdmin($this);
    }

    /**
     * Determine if the user is a member of the given household.
     */
    public function isMemberOf(Household $household): bool
    {
        return $household->isMember($this);
    }

    /** @return HasMany<ShoppingList, $this> */
    public function createdLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class, 'created_by');
    }

    /** @return HasMany<Product, $this> */
    public function createdProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    /** @return HasMany<Recipe, $this> */
    public function createdRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'created_by');
    }

    /** @return HasMany<Recipe, $this> */
    public function ownerRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'owner_id');
    }

    /** @return HasMany<RecipeCollection, $this> */
    public function recipeCollections(): HasMany
    {
        return $this->hasMany(RecipeCollection::class, 'owner_id');
    }

    /**
     * Assign a role to the user by role model or slug.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Sync roles for the user by role slug.
     */
    public function syncRoles(string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $this->roles()->sync([$role->id]);
    }

    /**
     * Determine if the user has confirmed two-factor authentication.
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /** @return HasOne<GoogleIdentity, $this> */
    public function googleIdentity(): HasOne
    {
        return $this->hasOne(GoogleIdentity::class);
    }

    public function canAccessApplication(): bool
    {
        return $this->approval_status === 'approved'
            && ($this->googleIdentity()->doesntExist() || $this->roles()->exists());
    }
}
