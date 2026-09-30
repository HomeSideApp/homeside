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
        return $this->roles()
            ->with('permissions:id,route_name')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('route_name')
            ->unique()
            ->values();
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
