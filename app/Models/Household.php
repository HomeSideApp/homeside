<?php

namespace App\Models;

use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use Database\Factories\HouseholdFactory;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\ModuleAiConfiguration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class Household
 *
 * This class represents a household in the application and extends the Model class.
 * It uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the household (UUID).
 * @property string $name The name of the household.
 * @property string|null $invite_code The invite code used to join the household.
 * @property array<string, mixed> $settings The settings of the household.
 * @property string|null $created_by The id of the user who created the household.
 * @property string|null $description The description of the household.
 * @property string|null $image_url The URL of the household image.
 * @property string|null $color The color of the household.
 * @property Carbon|null $created_at The timestamp when the household was created.
 * @property Carbon|null $updated_at The timestamp when the household was last updated.
 *
 * Relationships:
 * @property User|null $creator The user who created the household.
 * @property Collection<int, HouseholdMember> $members The members of the household.
 * @property Collection<int, User> $users The users belonging to the household.
 * @property Collection<int, User> $activeUsers The users whose active household is this one.
 * @property Collection<int, HouseholdInvitation> $invitations The invitations sent to join the household.
 * @property Collection<int, ShoppingList> $lists The shopping lists of the household.
 * @property Collection<int, Recipe> $recipes The recipes shared with the household.
 * @property Collection<int, HouseholdRecipe> $householdRecipes The recipe references of the household.
 * @property Collection<int, HouseholdModule> $modules The module configurations of the household.
 * @property Collection<int, Tag> $tags The tags of the household.
 * @property Collection<int, AiProvider> $aiProviders The AI providers of the household.
 * @property Collection<int, ModuleAiConfiguration> $moduleConfigurations The module AI configurations of the household.
 * @property Collection<int, HouseholdInvitation> $pendingInvitations The pending invitations of the household.
 *
 * @mixin Model
 */
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'invite_code', 'settings', 'created_by', 'description', 'image_url', 'color'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<HouseholdMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_members');
    }

    /** @return HasMany<User, $this> */
    public function activeUsers(): HasMany
    {
        return $this->hasMany(User::class, 'active_household_id');
    }

    /** @return HasMany<HouseholdInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }

    /** @return HasMany<ShoppingList, $this> */
    public function lists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    /** @return BelongsToMany<Recipe, $this> */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'household_recipes')
            ->withTimestamps();
    }

    /** @return HasMany<HouseholdRecipe, $this> */
    public function householdRecipes(): HasMany
    {
        return $this->hasMany(HouseholdRecipe::class);
    }

    /** @return HasMany<HouseholdModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(HouseholdModule::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'household_tag');
    }

    /** @return HasMany<AiProvider, $this> */
    public function aiProviders(): HasMany
    {
        return $this->hasMany(AiProvider::class);
    }

    /** @return HasMany<ModuleAiConfiguration, $this> */
    public function moduleConfigurations(): HasMany
    {
        return $this->hasMany(ModuleAiConfiguration::class);
    }

    /** @return HasMany<HouseholdInvitation, $this> */
    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now());
    }

    /** @return HasMany<HouseholdInviteLink, $this> */
    public function inviteLinks(): HasMany
    {
        return $this->hasMany(HouseholdInviteLink::class);
    }

    /**
     * Determine if the given user is an admin of the household.
     */
    public function isAdmin(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('role', HouseholdRole::Admin)
            ->exists();
    }

    /**
     * Determine if the given user is a member of the household.
     */
    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Generate a unique invite code for the household.
     */
    public function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Determine if the given module is enabled for the household.
     */
    public function isModuleEnabled(HouseholdModuleEnum $module): bool
    {
        return $this->modules()
            ->where('module', $module->value)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * @return Collection<int, HouseholdModule>
     */
    public function getEnabledModules(): Collection
    {
        return $this->modules->filter(fn (HouseholdModule $module): bool => $module->enabled);
    }
}
