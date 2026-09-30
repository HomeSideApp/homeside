<?php

namespace App\Models;

use Database\Factories\ShoppingListFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class ShoppingList
 *
 * This class represents a shopping list that belongs to a user and optionally a household.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the shopping list (UUID).
 * @property string $name The name of the shopping list.
 * @property string $created_by The id of the user who created the shopping list.
 * @property string|null $household_id The id of the household the shopping list belongs to, if any.
 * @property Carbon|null $created_at The timestamp when the shopping list was created.
 * @property Carbon|null $updated_at The timestamp when the shopping list was last updated.
 *
 * Relationships:
 * @property User|null $creator The user who created the shopping list.
 * @property Household|null $household The household the shopping list belongs to, if any.
 * @property Collection<int, ListItem> $items The list items of the shopping list.
 *
 * @mixin Model
 */
class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'created_by', 'household_id'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return HasMany<ListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }
}
