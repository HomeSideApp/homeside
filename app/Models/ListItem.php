<?php

namespace App\Models;

use Database\Factories\ListItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class ListItem
 *
 * This class represents an item within a shopping list, referencing a product or a custom name.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the list item (UUID).
 * @property string $list_id The id of the shopping list the item belongs to.
 * @property string|null $product_id The id of the product referenced by the item, if any.
 * @property string|null $custom_name The custom name of the item, if no product is referenced.
 * @property float $quantity The quantity of the item.
 * @property string|null $unit The unit of the item, if any.
 * @property bool $is_checked Whether the item is checked off the list.
 * @property string|null $notes Additional notes of the item, if any.
 * @property string|null $store_id The id of the store associated with the item, if any.
 * @property string|null $image_url The URL of the item image, if any.
 * @property string|null $icon The icon of the item, if any.
 * @property string|null $category_id The id of the category of the item, if any.
 * @property string|null $added_by The id of the user who added the item, if any.
 * @property string|null $source_type The source type of the item, if any.
 * @property string|null $source_id The id of the source of the item, if any.
 * @property float|null $original_quantity The original quantity of the item before scaling, if any.
 * @property string|null $original_unit The original unit of the item before scaling, if any.
 * @property int|null $scaled_servings The number of servings the item was scaled for, if any.
 * @property Carbon|null $created_at The timestamp when the item was created.
 * @property Carbon|null $updated_at The timestamp when the item was last updated.
 *
 * Relationships:
 * @property ShoppingList|null $list The shopping list the item belongs to.
 * @property Product|null $product The product referenced by the item, if any.
 * @property Store|null $store The store associated with the item, if any.
 * @property Category|null $category The category of the item, if any.
 * @property User|null $addedByUser The user who added the item.
 *
 * @mixin Model
 */
class ListItem extends Model
{
    /** @use HasFactory<ListItemFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['list_id', 'product_id', 'custom_name', 'quantity', 'unit', 'is_checked', 'notes', 'store_id', 'image_url', 'icon', 'category_id', 'added_by', 'source_type', 'source_id', 'original_quantity', 'original_unit', 'scaled_servings'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'is_checked' => 'boolean',
            'original_quantity' => 'float',
        ];
    }

    /** @return BelongsTo<ShoppingList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
