<?php

namespace App\Models;

use App\Concerns\Translatable;
use App\Contracts\Translatable as TranslatableContract;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class Tag
 *
 * This class represents a tag that can be attached to a household.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the tag (UUID).
 * @property string $name The name of the tag.
 * @property string $slug The unique slug of the tag.
 * @property string $type The type of the tag.
 * @property Carbon|null $created_at The timestamp when the tag was created.
 * @property Carbon|null $updated_at The timestamp when the tag was last updated.
 *
 * Relationships:
 * @property Collection<int, Household> $households The households the tag is attached to.
 * @property Collection<int, Translation> $translations The translations of the tag.
 * @property Collection<int, TranslationStatus> $translationStatus The translation publication statuses of the tag by locale.
 *
 * @mixin Model
 */
class Tag extends Model implements TranslatableContract
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, HasUuids, Translatable;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['name', 'slug', 'type'];

    /** @return BelongsToMany<Household, $this> */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_tag');
    }

    /**
     * Bootstrap the model, auto-generating a slug when one is missing.
     */
    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }
}
