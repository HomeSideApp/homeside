<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class PermissionGroup
 *
 * This class represents a permission group in the system that organizes related permissions.
 * This model manages the grouping of permissions and their associations with modules.
 * It extends the Eloquent Model class and uses the HasTimestamps and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the permission group (UUID).
 * @property string $name The name of the permission group.
 * @property string|null $description The optional description of the permission group.
 * @property Carbon|null $created_at The timestamp when the permission group was created.
 * @property Carbon|null $updated_at The timestamp when the permission group was last updated.
 *
 * Relationships:
 * @property Collection<int, Permission> $permissions The permissions that belong to this permission group.
 *
 * @mixin Model
 */
class PermissionGroup extends Model
{
    use HasTimestamps, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get all permissions that belong to this permission group.
     *
     * @return HasMany<Permission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }
}
