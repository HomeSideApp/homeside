<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAdminRoleRequest;
use App\Http\Requests\IndexAdminRolesRequest;
use App\Http\Requests\SaveAdminRoleApiRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AdminRoleController extends Controller
{
    public function index(IndexAdminRolesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $direction = $validated['direction'] ?? 'asc';
        $roles = Role::query()->with('permissions:id,route_name')->withCount('users')
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($validated['sort'] ?? 'name', $direction)->orderBy('id', $direction)
            ->paginate($validated['perPage'] ?? 15)->withQueryString();

        return response()->json([
            'data' => collect($roles->items())->map(fn (Role $role) => $this->resource($role)),
            'links' => ['prev' => $roles->previousPageUrl(), 'next' => $roles->nextPageUrl()],
            'meta' => ['current_page' => $roles->currentPage(), 'last_page' => $roles->lastPage(), 'per_page' => $roles->perPage(), 'total' => $roles->total()],
        ]);
    }

    public function store(SaveAdminRoleApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $role = Role::create(['name' => $validated['name'], 'slug' => Str::slug($validated['name'])]);
        $this->syncPermissions($role, $validated['permissions']);

        return response()->json(['data' => $this->resource($role->load('permissions'))], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => $this->resource($role->load('permissions'))]);
    }

    public function update(SaveAdminRoleApiRequest $request, Role $role): JsonResponse
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $role): void {
            if (isset($validated['permissions'])) {
                $this->syncPermissions($role, $validated['permissions']);
            }
            if (isset($validated['name'])) {
                $role->update(['name' => $validated['name']]);
            }
        }, 3);

        return response()->json(['data' => $this->resource($role->load('permissions'))]);
    }

    public function destroy(DeleteAdminRoleRequest $request, Role $role): Response
    {
        DB::transaction(fn () => $role->delete(), 3);

        return response()->noContent();
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::query()->with('permissionsGroup')->orderBy('route_name')->get()->map(fn (Permission $permission): array => [
            'id' => $permission->id,
            'name' => $permission->route_name,
            'group' => $permission->permissionsGroup?->name,
            'label' => $permission->name,
            'description' => $permission->description,
        ]);

        return response()->json(['data' => $permissions]);
    }

    /** @param list<string> $routeNames */
    private function syncPermissions(Role $role, array $routeNames): void
    {
        $role->syncPermissions(Permission::query()->whereIn('route_name', $routeNames)->get());
    }

    /** @return array<string, mixed> */
    private function resource(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'is_system' => $role->is_system,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'permissions' => $role->permissions->pluck('route_name')->values(),
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }
}
