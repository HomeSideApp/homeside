<?php

namespace App\Traits;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasPermissionsTrait
{
    public function hasRole(string ...$roleSlugs): bool
    {
        return $this->roles()
            ->whereIn('slug', $roleSlugs)
            ->exists();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return Collection<int, string>
     */
    public function getPermissionRouteNames(): Collection
    {
        $key = 'global_permissions.'.$this->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $permissions = $this->roles()
            ->with('permissions:id,route_name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('route_name')
            ->unique()
            ->values();
        request()->attributes->set($key, $permissions);

        return $permissions;
    }

    /**
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return $this->roles()
            ->pluck('slug')
            ->unique()
            ->values();
    }
}
