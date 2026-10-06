<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAdminUsersRequest;
use App\Http\Requests\SaveAdminUserApiRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\PlatformAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class AdminUserController extends Controller
{
    public function index(IndexAdminUsersRequest $request): JsonResponse
    {
        $validated = $request->validated();
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

    public function store(SaveAdminUserApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'locale' => $validated['locale'],
            'password' => Str::random(64),
        ]);
        app(PlatformAccess::class)->assignRoles($this->authenticatedUser($request), $user, $validated['roles'] ?? [$validated['role']]);
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

    public function update(SaveAdminUserApiRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        $updates = collect($validated)->only(['name', 'email', 'locale', 'can_create_households'])->all();
        $emailChanged = isset($validated['email']) && $validated['email'] !== $user->email;
        $user->fill($updates);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        DB::transaction(function () use ($user, $request, $validated): void {
            $user->save();
            if (isset($validated['roles']) || isset($validated['role'])) {
                app(PlatformAccess::class)->assignRoles($this->authenticatedUser($request), $user, $validated['roles'] ?? [$validated['role']]);
            }

        }, 3);

        return response()->json(['data' => $this->resource($user->load('roles'))]);
    }

    public function destroy(User $user): Response
    {
        DB::transaction(fn () => $user->delete());

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function resource(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'can_create_households' => $user->can_create_households,
            'roles' => $user->roles->pluck('slug')->values(),
            'approval_status' => $user->approval_status,
            'google_connected' => $user->googleIdentity !== null,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
