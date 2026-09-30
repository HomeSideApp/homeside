<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Recipe
 *
 * This class represents a recipe that can be owned by a user and shared with households.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the recipe (UUID).
 * @property string $name The name of the recipe.
 * @property string|null $description The description of the recipe, if any.
 * @property int|null $servings The number of servings of the recipe, if any.
 * @property string|null $created_by The id of the user who created the recipe, if any.
 * @property string|null $owner_id The id of the user who owns the recipe, if any.
 * @property string|null $derived_from_recipe_id The id of the recipe this recipe was derived from, if any.
 * @property string|null $collection_id The id of the recipe collection the recipe belongs to, if any.
 * @property string|null $cover_image_path The path of the cover image of the recipe, if any.
 * @property string|null $yield_text The yield text of the recipe, if any.
 * @property int|null $prep_time_seconds The preparation time of the recipe in seconds, if any.
 * @property int|null $cook_time_seconds The cooking time of the recipe in seconds, if any.
 * @property int|null $total_time_seconds The total time of the recipe in seconds, if any.
 * @property string|null $difficulty The difficulty level of the recipe, if any.
 * @property string|null $cuisine The cuisine of the recipe, if any.
 * @property string|null $locale The locale of the recipe, if any.
 * @property string|null $cooking_method The cooking method of the recipe, if any.
 * @property string|null $recipe_category The category of the recipe, if any.
 * @property array<int, string> $suitable_for_diet The diets the recipe is suitable for.
 * @property array<int, string> $keywords The keywords of the recipe.
 * @property string|null $author The author of the recipe, if any.
 * @property string|null $source_url The source URL of the recipe, if any.
 * @property string|null $source_name The source name of the recipe, if any.
 * @property string|null $source_type The source type of the recipe, if any.
 * @property string|null $notes Additional notes of the recipe, if any.
 * @property Carbon|null $created_at The timestamp when the recipe was created.
 * @property Carbon|null $updated_at The timestamp when the recipe was last updated.
 *
 * Relationships:
 * @property User|null $creator The user who created the recipe.
 * @property User|null $owner The user who owns the recipe.
 * @property Recipe|null $derivedFrom The recipe this recipe was derived from.
 * @property Collection<int, Recipe> $derivedRecipes The recipes derived from this recipe.
 * @property RecipeCollection|null $collection The recipe collection the recipe belongs to.
 * @property Collection<int, RecipeReference> $references The references of this recipe.
 * @property Collection<int, RecipeReference> $referencedBy The recipes referencing this recipe.
 * @property Collection<int, RecipeSection> $sections The sections of the recipe.
 * @property Collection<int, RecipeIngredient> $ingredients The ingredients of the recipe.
 * @property Collection<int, RecipeStep> $steps The steps of the recipe.
 * @property Collection<int, RecipeCookware> $cookware The cookware of the recipe.
 * @property Collection<int, RecipeTag> $tags The tags of the recipe.
 * @property Collection<int, HouseholdRecipe> $householdShares The household shares of the recipe.
 * @property Collection<int, Household> $households The households the recipe is shared with.
 *
 * @mixin Model
 */
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory, HasUuids;

    protected $appends = ['collection_path'];

    protected $fillable = [
        'name',
        'description',
        'servings',
        'created_by',
        'owner_id',
        'derived_from_recipe_id',
        'collection_id',
        'cover_image_path',
        'yield_text',
        'prep_time_seconds',
        'cook_time_seconds',
        'total_time_seconds',
        'difficulty',
        'cuisine',
        'locale',
        'cooking_method',
        'recipe_category',
        'suitable_for_diet',
        'keywords',
        'author',
        'source_url',
        'source_name',
        'source_type',
        'notes',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'suitable_for_diet' => 'array',
            'keywords' => 'array',
            'prep_time_seconds' => 'integer',
            'cook_time_seconds' => 'integer',
            'total_time_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Recipe, $this> */
    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_recipe_id');
    }

    /** @return HasMany<Recipe, $this> */
    public function derivedRecipes(): HasMany
    {
        return $this->hasMany(self::class, 'derived_from_recipe_id');
    }

    /** @return BelongsTo<RecipeCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(RecipeCollection::class, 'collection_id');
    }

    /**
     * Get the recipe's collection path when its collection is loaded.
     */
    public function getCollectionPathAttribute(): ?string
    {
        return $this->relationLoaded('collection') ? $this->collection?->path : null;
    }

    /** @return HasMany<RecipeReference, $this> */
    public function references(): HasMany
    {
        return $this->hasMany(RecipeReference::class)->orderBy('order');
    }

    /** @return HasMany<RecipeReference, $this> */
    public function referencedBy(): HasMany
    {
        return $this->hasMany(RecipeReference::class, 'referenced_recipe_id');
    }

    /** @return HasMany<RecipeSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(RecipeSection::class)->orderBy('order');
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('order');
    }

    /** @return HasMany<RecipeStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('order');
    }

    /** @return HasMany<RecipeCookware, $this> */
    public function cookware(): HasMany
    {
        return $this->hasMany(RecipeCookware::class)->orderBy('order');
    }

    /** @return HasMany<RecipeTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(RecipeTag::class);
    }

    /** @return HasMany<HouseholdRecipe, $this> */
    public function householdShares(): HasMany
    {
        return $this->hasMany(HouseholdRecipe::class);
    }

    /** @return BelongsToMany<Household, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_recipes')
            ->withTimestamps();
    }

    /**
     * Scope the query to recipes visible to the given user, either because
     * they own them or because they are shared with a household they belong to.
     *
     * Mirrors RecipePolicy::view so every read surface (UI, API and AI tools)
     * resolves the same visibility rules.
     *
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('households', function (Builder $hq) use ($user) {
                    $hq->whereHas('members', function (Builder $mq) use ($user) {
                        $mq->where('user_id', $user->id);
                    });
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $array['cover_image_url'] = ! empty($array['cover_image_path'])
            ? route('images.show', ['type' => 'recipe', 'uuid' => $this->id])
            : null;

        if (isset($array['tags']) && is_array($array['tags'])) {
            $array['tags'] = array_map(
                fn (array $tag) => $tag['name'] ?? '',
                $array['tags']
            );
        }

        return $array;
    }
}
