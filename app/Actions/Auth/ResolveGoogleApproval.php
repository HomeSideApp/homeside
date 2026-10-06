<?php

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\GoogleAccessResolved;
use App\Support\PlatformAccess;
use Illuminate\Support\Facades\DB;

final class ResolveGoogleApproval
{
    /** @param list<string> $roleSlugs */
    public function approve(User $user, array $roleSlugs): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && app(PlatformAccess::class)->canManage($actor), 403);
        abort_unless($user->googleIdentity()->exists(), 409);
        $roles = Role::query()->whereIn('slug', $roleSlugs)->pluck('id');
        abort_unless($roleSlugs !== [] && $roles->count() === count(array_unique($roleSlugs)), 422);

        $wasResolved = DB::transaction(function () use ($user, $roleSlugs, $actor): bool {
            Role::query()->where('slug', 'admin')->lockForUpdate()->first();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->approval_status === 'approved') {
                return false;
            }

            app(PlatformAccess::class)->assignRoles($actor, $lockedUser, $roleSlugs);
            $lockedUser->forceFill(['approval_status' => 'approved', 'approved_at' => now()])->save();

            return true;
        }, 3);

        if ($wasResolved) {
            $this->notify($user, true);
        }
    }

    private function notify(User $user, bool $approved): void
    {
        try {
            $user->notify(new GoogleAccessResolved($approved));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function reject(User $user): void
    {
        abort_unless(auth()->user() && app(PlatformAccess::class)->canManage(auth()->user()), 403);
        abort_unless($user->googleIdentity()->exists() && $user->approval_status !== 'approved', 409);
        DB::transaction(function () use ($user): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->first();
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($lockedUser->approval_status !== 'approved', 409);
            $lockedUser->roles()->detach();
            $lockedUser->forceFill(['approval_status' => 'rejected', 'approved_at' => null])->save();
            $lockedUser->tokens()->delete();
        }, 3);
        $this->notify($user, false);
    }
}
