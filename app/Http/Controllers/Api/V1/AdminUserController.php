<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppLocale;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', 'string', 'exists:roles,slug'],
            'approval' => ['sometimes', 'in:pending,approved,rejected'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,email,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);
        $direction = $validated['direction'] ?? 'desc';
        $users = User::query()
            ->with(['roles:id,name,slug', 'googleIdentity'])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($validated['role'] ?? null, fn ($query, string $role) => $query->whereHas('roles', fn ($roles) => $roles->where('slug', $role)))
            ->when($validated['approval'] ?? null, fn ($query, string $approval) => $query->where('approval_status', $approval))
            ->orderBy($validated['sort'] ?? 'created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user) => $this->resource($user)),
            'links' => ['first' => $users->url(1), 'last' => $users->url($users->lastPage()), 'prev' => $users->previousPageUrl(), 'next' => $users->nextPageUrl()],
            'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUser($request);
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'locale' => $validated['locale'],
            'password' => Str::random(64),
        ]);
        $this->syncRoles($user, $validated['roles']);
        $token = Password::broker()->createToken($user);
        $url = rtrim((string) config('app.mobile_url'), '/').'/set-password?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);
        $user->notify(new ResetPasswordNotification($user, $url));

        return response()->json(['data' => $this->resource($user->load('roles'))], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $this->resource($user->load('roles'))]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $this->validateUser($request, $user);
        $removesLastAdmin = $user->hasRole('admin')
            && isset($validated['roles'])
            && ! in_array('admin', $validated['roles'], true)
            && User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->count() === 1;
        abort_if($removesLastAdmin, 409, 'The final administrative access cannot be removed.');
        $updates = collect($validated)->only(['name', 'email', 'locale'])->all();
        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        $user->fill($updates);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if (isset($validated['roles'])) {
            $this->syncRoles($user, $validated['roles']);
        }

        return response()->json(['data' => $this->resource($user->load('roles'))]);
    }

    public function destroy(User $user): Response
    {
        abort_if($user->hasRole('admin') && User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->count() === 1, 409, 'The final administrator cannot be deleted.');
        $user->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $presence = $user === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'email' => [$presence, 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'locale' => [$presence, Rule::enum(AppLocale::class)],
            'roles' => [$presence, 'array', 'min:1'],
            'roles.*' => ['string', 'distinct', 'exists:roles,slug'],
        ]);
    }

    /** @param list<string> $roleSlugs */
    private function syncRoles(User $user, array $roleSlugs): void
    {
        $user->roles()->sync(Role::query()->whereIn('slug', $roleSlugs)->pluck('id'));
    }

    /** @return array<string, mixed> */
    private function resource(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'roles' => $user->roles->pluck('slug')->values(),
            'approval_status' => $user->approval_status,
            'google_connected' => $user->googleIdentity !== null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
