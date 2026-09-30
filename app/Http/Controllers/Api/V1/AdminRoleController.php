<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminRoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);
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

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRole($request);
        $role = Role::create(['name' => $validated['name'], 'slug' => Str::slug($validated['name'])]);
        $this->syncPermissions($role, $validated['permissions']);

        return response()->json(['data' => $this->resource($role->load('permissions'))], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json(['data' => $this->resource($role->load('permissions'))]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->isSystem(), 409, 'System roles cannot be modified.');
        $validated = $this->validateRole($request, $role);
        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }
        if (isset($validated['permissions'])) {
            $this->syncPermissions($role, $validated['permissions']);
        }

        return response()->json(['data' => $this->resource($role->load('permissions'))]);
    }

    public function destroy(Role $role): Response
    {
        abort_if($role->isSystem(), 409, 'System roles cannot be deleted.');
        abort_if($role->users()->exists(), 409, 'A role assigned to users cannot be deleted.');
        $role->delete();

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

    /** @return array<string, mixed> */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        $presence = $role === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => [$presence, 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,route_name'],
        ]);
    }

    /** @param list<string> $routeNames */
    private function syncPermissions(Role $role, array $routeNames): void
    {
        $role->permissions()->sync(Permission::query()->whereIn('route_name', $routeNames)->pluck('id'));
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
