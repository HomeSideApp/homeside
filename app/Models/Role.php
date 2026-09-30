<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class Role
 *
 * This class represents a role in the application and extends the Model class.
 * It uses the HasUuids trait.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the role (UUID).
 * @property string $name The name of the role.
 * @property string $slug The slug for the role.
 * @property bool $is_system Whether this is a system role that cannot be edited or deleted.
 * @property Carbon|null $created_at The timestamp when the role was created.
 * @property Carbon|null $updated_at The timestamp when the role was last updated.
 *
 * Relationships:
 * @property Collection<int, Permission> $permissions The permissions associated with the role.
 * @property Collection<int, User> $users The users associated with the role.
 *
 * @mixin Model
 */
class Role extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'slug', 'is_system'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Get the permissions associated with the role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'roles_permissions');
    }

    /**
     * Get the users associated with the role.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Sync permissions for the role by permission names or Permission models.
     *
     * @param  iterable<int, Permission|string>  $permissions
     */
    public function syncPermissions(iterable $permissions): void
    {
        $permissionIds = collect($permissions)->map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission->id;
            }

            return Permission::where('name', $permission)->first()?->id;
        })->filter()->values()->all();

        $this->permissions()->sync($permissionIds);
    }

    /**
     * Give permissions to the role by permission names or Permission models.
     *
     * @param  iterable<int, Permission|string>  $permissions
     */
    public function givePermissionTo(iterable $permissions): void
    {
        $permissionIds = collect($permissions)->map(function ($permission) {
            if ($permission instanceof Permission) {
                return $permission->id;
            }

            return Permission::where('name', $permission)->first()?->id;
        })->filter()->values()->all();

        $this->permissions()->syncWithoutDetaching($permissionIds);
    }

    /**
     * Check if the role has a specific permission by name.
     */
    public function hasPermissionTo(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }

    /**
     * Determine if this is a system role.
     */
    public function isSystem(): bool
    {
        return $this->is_system === true;
    }
}
