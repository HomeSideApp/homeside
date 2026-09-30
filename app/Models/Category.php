<?php

namespace App\Models;

use App\Concerns\Translatable;
use App\Contracts\Translatable as TranslatableContract;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Category
 *
 * This class represents a category used to group products and list items.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the category (UUID).
 * @property string $name The name of the category.
 * @property string $slug The unique slug of the category.
 * @property string|null $icon The icon of the category, if any.
 * @property string|null $color The color of the category, if any.
 * @property bool $is_active Whether the category is active.
 * @property int $sort_order The sort order of the category.
 * @property Carbon|null $created_at The timestamp when the category was created.
 * @property Carbon|null $updated_at The timestamp when the category was last updated.
 *
 * Relationships:
 * @property Collection<int, Product> $products The products belonging to the category.
 * @property Collection<int, ListItem> $listItems The list items belonging to the category.
 * @property Collection<int, Translation> $translations The translations of the category.
 * @property Collection<int, TranslationStatus> $translationStatus The translation publication statuses of the category by locale.
 *
 * @mixin Model
 */
class Category extends Model implements TranslatableContract
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids, Translatable;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'icon', 'color', 'is_active', 'sort_order'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<ListItem, $this> */
    public function listItems(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }
}
