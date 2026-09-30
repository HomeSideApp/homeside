<?php

namespace App\Services\Auth;

use App\Models\GoogleIdentity;
use App\Models\User;
use App\Notifications\GoogleAccessRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GoogleIdentityService
{
    /** @return array{status: string, user: User|null} */
    public function resolve(string $sub, string $email, string $name, bool $verified): array
    {
        if ($sub === '' || $email === '' || ! $verified) {
            throw ValidationException::withMessages(['google' => 'Google no ha verificado esta dirección de correo.']);
        }

        $identity = GoogleIdentity::query()->with('user')->where('google_sub', $sub)->first();
        if ($identity !== null) {
            $user = $identity->user;
            if ($user === null) {
                throw new \RuntimeException('Google identity has no user.');
            }

            return ['status' => $user->approval_status, 'user' => $user];
        }

        if (User::query()->where('email', Str::lower($email))->exists()) {
            return ['status' => 'link_required', 'user' => null];
        }

        $user = DB::transaction(function () use ($sub, $email, $name): User {
            $user = User::create([
                'name' => $name !== '' ? $name : $email,
                'email' => Str::lower($email),
                'email_verified_at' => now(),
                'password' => Str::random(80),
                'approval_status' => 'pending',
            ]);
            $user->googleIdentity()->create(['google_sub' => $sub]);

            return $user;
        });

        try {
            Notification::send(
                User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))->get(),
                new GoogleAccessRequested($user),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return ['status' => 'pending', 'user' => $user];
    }

    public function link(User $user, string $sub, string $email, bool $verified): void
    {
        if (! $verified || ! hash_equals(Str::lower($user->email), Str::lower($email))) {
            throw ValidationException::withMessages(['google' => 'La cuenta Google debe tener el mismo correo verificado que tu perfil.']);
        }
        if (GoogleIdentity::query()->where('google_sub', $sub)->where('user_id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['google' => 'Esta cuenta Google ya está vinculada a otra persona.']);
        }

        $user->googleIdentity()->updateOrCreate(['user_id' => $user->id], ['google_sub' => $sub]);
    }
}
