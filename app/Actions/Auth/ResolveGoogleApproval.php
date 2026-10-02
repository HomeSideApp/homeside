<?php

namespace App\Actions\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\GoogleAccessResolved;
use Illuminate\Support\Facades\DB;

final class ResolveGoogleApproval
{
    /** @param list<string> $roleSlugs */
    public function approve(User $user, array $roleSlugs): void
    {
        abort_unless($user->googleIdentity()->exists(), 409);
        $roles = Role::query()->whereIn('slug', $roleSlugs)->pluck('id');
        abort_unless($roleSlugs !== [] && $roles->count() === count(array_unique($roleSlugs)), 422);

        $wasResolved = DB::transaction(function () use ($user, $roles): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->approval_status === 'approved') {
                return false;
            }

            $lockedUser->roles()->sync($roles);
            $lockedUser->forceFill(['approval_status' => 'approved', 'approved_at' => now()])->save();

            return true;
        });

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
        abort_unless($user->googleIdentity()->exists() && $user->approval_status !== 'approved', 409);
        DB::transaction(function () use ($user): void {
            $user->roles()->detach();
            $user->forceFill(['approval_status' => 'rejected', 'approved_at' => null])->save();
            $user->tokens()->delete();
        });
        $this->notify($user, false);
    }
}
