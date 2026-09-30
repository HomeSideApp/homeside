<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class Permission
 *
 * This class represents a permission in the application and extends the Model class.
 * It uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the permission (UUID).
 * @property string $name The name of the permission.
 * @property string $route_name The route protected by the permission.
 * @property string|null $description The description of the permission, if any.
 * @property string|null $permission_group_id The id of the permission group the permission belongs to, if any.
 * @property Carbon|null $created_at The timestamp when the permission was created.
 * @property Carbon|null $updated_at The timestamp when the permission was last updated.
 *
 * Relationships:
 * @property Collection<int, Role> $roles The roles that have this permission.
 * @property PermissionGroup|null $permissionsGroup The group that the permission belongs to.
 *
 * @mixin Model
 */
class Permission extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'route_name', 'description', 'permission_group_id'];

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'roles_permissions');
    }

    /** @return BelongsTo<PermissionGroup, $this> */
    public function permissionsGroup(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }
}
