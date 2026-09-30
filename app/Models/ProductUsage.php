<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class ProductUsage
 *
 * This class represents a record that tracks how often and when a user has used a product.
 * It extends the Eloquent Model class and uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the usage record (UUID).
 * @property string $user_id The id of the user who used the product.
 * @property string $product_id The id of the product that was used.
 * @property string|null $list_id The id of the shopping list associated with the usage, if any.
 * @property Carbon|null $last_used_at The timestamp when the product was last used.
 * @property int $usage_count The number of times the product has been used.
 * @property Carbon|null $created_at The timestamp when the usage record was created.
 * @property Carbon|null $updated_at The timestamp when the usage record was last updated.
 *
 * Relationships:
 * @property User|null $user The user who used the product.
 * @property Product|null $product The product that was used.
 * @property ShoppingList|null $list The shopping list associated with the usage, if any.
 *
 * @mixin Model
 */
class ProductUsage extends Model
{
    use HasUuids;

    protected $table = 'product_usage';

    protected $fillable = ['user_id', 'product_id', 'list_id', 'last_used_at', 'usage_count'];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'usage_count' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ShoppingList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }
}
