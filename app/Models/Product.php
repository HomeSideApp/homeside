<?php

namespace App\Models;

use App\Concerns\Translatable;
use App\Contracts\Translatable as TranslatableContract;
use App\Enums\AppLocale;
use App\Enums\TranslationEntityStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Product
 *
 * This class represents a purchasable product that can be added to shopping lists.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the product (UUID).
 * @property string $name The name of the product.
 * @property string $slug The unique slug of the product.
 * @property string|null $category_id The id of the category the product belongs to, if any.
 * @property string|null $icon The icon of the product, if any.
 * @property bool $is_active Whether the product is active.
 * @property bool $is_personal Whether the product is a personal (user-created) product.
 * @property string|null $created_by The id of the user who created the product, if any.
 * @property Carbon|null $created_at The timestamp when the product was created.
 * @property Carbon|null $updated_at The timestamp when the product was last updated.
 *
 * Relationships:
 * @property User|null $creator The user who created the product.
 * @property Category|null $category The category the product belongs to.
 * @property Collection<int, ProductImage> $images The images of the product.
 * @property Collection<int, ListItem> $listItems The list items referencing the product.
 * @property Collection<int, RecipeIngredient> $recipeIngredients The recipe ingredients referencing the product.
 * @property Collection<int, ProductUsage> $usages The usage records of the product.
 * @property Collection<int, Translation> $translations The translations of the product.
 * @property Collection<int, TranslationStatus> $translationStatus The translation publication statuses of the product by locale.
 *
 * @mixin Model
 */
class Product extends Model implements TranslatableContract
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUuids, Translatable;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'category_id', 'icon', 'image_url', 'needs_image', 'is_approved', 'is_active', 'is_personal', 'created_by'];

    /** @param Builder<self> $query */
    public function scopeMatchingName(Builder $query, string $name, bool $exact = false): void
    {
        $operator = $exact ? '=' : 'like';
        $value = $exact ? $name : "%{$name}%";
        $locales = array_unique([
            AppLocale::resolve(app()->getLocale())->value,
            AppLocale::resolve(config('app.fallback_locale'))->value,
        ]);

        $query->where(function (Builder $names) use ($operator, $value, $locales): void {
            $names->where('name', $operator, $value);

            foreach ($locales as $locale) {
                $names->orWhere(function (Builder $translated) use ($operator, $value, $locale): void {
                    $translated->whereHas('translationStatus', fn (Builder $statuses) => $statuses->where('locale', $locale)
                        ->where('status', TranslationEntityStatus::Published->value))
                        ->whereHas('translations', fn (Builder $translations) => $translations->where('locale', $locale)
                            ->where('field', 'name')->where('value', $operator, $value));
                });
            }
        });
    }

    /** @param Builder<self> $query */
    public function scopeVisibleToUser(Builder $query, ?string $userId): void
    {
        $query->where(function (Builder $visible) use ($userId): void {
            $visible->where('is_personal', false);

            if ($userId !== null) {
                $visible->orWhere(fn (Builder $personal) => $personal->where('is_personal', true)->where('created_by', $userId));
            }
        });
    }

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_personal' => 'boolean',
            'needs_image' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    /** @return HasMany<ListItem, $this> */
    public function listItems(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /** @return HasMany<ProductUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(ProductUsage::class);
    }
}
