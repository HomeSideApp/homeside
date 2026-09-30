<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SecurityConfirmation
{
    public static function issue(User $user): array
    {
        $plainTextToken = Str::random(64);
        Cache::put(self::key($plainTextToken), $user->id, now()->addMinutes(10));

        return [
            'token' => $plainTextToken,
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ];
    }

    public static function authorize(Request $request, User $user): void
    {
        $token = $request->header('X-Security-Confirmation');
        abort_unless(is_string($token) && hash_equals($user->id, (string) Cache::get(self::key($token))), 403, 'A recent security confirmation is required.');
    }

    public static function consume(Request $request): void
    {
        $token = $request->header('X-Security-Confirmation');

        if (is_string($token)) {
            Cache::forget(self::key($token));
        }
    }

    private static function key(string $token): string
    {
        return 'security-confirmation:'.hash('sha256', $token);
    }
}
