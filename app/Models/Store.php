<?php

namespace App\Models;

use App\Concerns\Translatable;
use App\Contracts\Translatable as TranslatableContract;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class Store
 *
 * This class represents a store where shopping can be done.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the store (UUID).
 * @property string $name The name of the store.
 * @property string $slug The unique slug of the store.
 * @property string|null $icon The icon of the store, if any.
 * @property string|null $color The color of the store, if any.
 * @property Carbon|null $created_at The timestamp when the store was created.
 * @property Carbon|null $updated_at The timestamp when the store was last updated.
 *
 * Relationships:
 * @property Collection<int, ListItem> $listItems The shopping-list items associated with the store.
 * @property Collection<int, Translation> $translations The translations of the store.
 * @property Collection<int, TranslationStatus> $translationStatus The translation publication statuses of the store by locale.
 *
 * @mixin Model
 */
class Store extends Model implements TranslatableContract
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasUuids, Translatable;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'icon', 'color'];

    /** @return HasMany<ListItem, $this> */
    public function listItems(): HasMany
    {
        return $this->hasMany(ListItem::class);
    }
}
