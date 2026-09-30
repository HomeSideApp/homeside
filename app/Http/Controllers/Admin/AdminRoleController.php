<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the admin role CRUD.
 */
class AdminRoleController extends Controller
{
    /**
     * List roles with optional search.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Request $request): Response
    {
        $query = Role::withCount('users', 'permissions');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->orderBy('name')
            ->paginate($request->input('perPage', 10))
            ->withQueryString();

        return Inertia::render('admin/Roles/Index', [
            'roles' => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_system' => $role->is_system,
            ]),
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    /**
     * Show a role's detail page.
     *
     * @param  Role  $role  The role model instance.
     * @return Response The HTTP response.
     */
    public function show(Role $role): Response
    {
        $groupMap = PermissionGroup::pluck('name', 'id')->toArray();

        return Inertia::render('admin/Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => $groupMap[$permission->permission_group_id] ?? 'Otros',
            ]),
        ]);
    }

    /**
     * Show the role creation form.
     *
     * @return Response The HTTP response.
     */
    public function create(): Response
    {
        $groupMap = PermissionGroup::pluck('name', 'id')->toArray();

        return Inertia::render('admin/Roles/Create', [
            'permissions' => Permission::all()->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => $groupMap[$permission->permission_group_id] ?? 'Otros',
            ]),
        ]);
    }

    /**
     * Create a new role.
     *
     * @param  StoreRoleRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')),
        ]);
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creado correctamente.']);

        return to_route('admin.roles');
    }

    /**
     * Show the role editing form.
     *
     * @param  Role  $role  The role model instance.
     * @return Response The HTTP response.
     */
    public function edit(Role $role): Response
    {
        $groupMap = PermissionGroup::pluck('name', 'id')->toArray();

        return Inertia::render('admin/Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => $groupMap[$permission->permission_group_id] ?? 'Otros',
            ]),
        ]);
    }

    /**
     * Update a role.
     *
     * @param  UpdateRoleRequest  $request  The incoming HTTP request.
     * @param  Role  $role  The role model instance.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->isSystem()) {
            abort(403, 'No se puede modificar un rol del sistema.');
        }

        $role->update([
            'name' => $request->validated('name'),
            'slug' => Str::slug($request->validated('name')),
        ]);
        $role->syncPermissions($request->validated('permissions'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualizado correctamente.']);

        return to_route('admin.roles');
    }

    /**
     * Delete a role.
     *
     * @param  Role  $role  The role model instance.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isSystem()) {
            abort(403, 'No se puede eliminar un rol del sistema.');
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminado correctamente.']);

        return to_route('admin.roles');
    }
}
