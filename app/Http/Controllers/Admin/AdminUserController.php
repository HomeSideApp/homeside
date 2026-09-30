<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the admin user CRUD.
 */
class AdminUserController extends Controller
{
    /**
     * List users with optional search.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Request $request): Response
    {
        $query = User::with(['roles', 'googleIdentity']);
        if ($request->query('approval') === 'pending') {
            $query->where('approval_status', 'pending');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('perPage', 10))
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles,
                'approval_status' => $user->approval_status,
                'google_connected' => $user->googleIdentity !== null,
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('admin/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'perPage', 'approval']),
            'roles' => Role::query()->orderBy('name')->get(['name', 'slug']),
            'pendingCount' => User::query()->where('approval_status', 'pending')->count(),
        ]);
    }

    /**
     * Show the user creation form.
     *
     * @return Response The HTTP response.
     */
    public function create(): Response
    {
        return Inertia::render('admin/Users/Create', [
            'roles' => Role::all(),
        ]);
    }

    /**
     * Create a new user.
     *
     * @param  StoreUserRequest  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->validated(),
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole($request->validated('role'));

        event(new Registered($user));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado correctamente.']);

        return to_route('admin.users');
    }

    /**
     * Show the user editing form.
     *
     * @param  User  $user  The authenticated user.
     * @return Response The HTTP response.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('admin/Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => Role::all(),
        ]);
    }

    /**
     * Update a user.
     *
     * @param  UpdateUserRequest  $request  The incoming HTTP request.
     * @param  User  $user  The authenticated user.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());
        $user->syncRoles($request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado correctamente.']);

        return to_route('admin.users');
    }

    /**
     * Delete a user.
     *
     * @param  User  $user  The authenticated user.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario eliminado correctamente.']);

        return to_route('admin.users');
    }
}
