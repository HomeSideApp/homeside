<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\AppLocale;
use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use PragmaRX\Google2FA\Google2FA;

final class PublicAccountController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
            'locale' => ['sometimes', Rule::enum(AppLocale::class)],
        ]);
        $user = User::create([
            ...$validated,
            'locale' => $validated['locale'] ?? AppLocale::default()->value,
        ]);
        $role = Role::query()->where('slug', 'user')->first();
        if ($role !== null) {
            $user->assignRole($role);
        }
        $user->notify(new MobileVerifyEmailNotification);

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->sendRecoveryNotification($validated['email'], 'password/reset');

        return response()->json(['message' => 'If the account exists, recovery instructions have been sent.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->completePasswordReset($request, false);
    }

    public function forgotOtp(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->sendRecoveryNotification($validated['email'], 'otp/reset');

        return response()->json(['message' => 'If the account exists, recovery instructions have been sent.'], 202);
    }

    public function setPassword(Request $request): JsonResponse
    {
        return $this->completePasswordReset($request, true);
    }

    private function completePasswordReset(Request $request, bool $verifyEmail): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);
        $status = Password::reset($validated, function (User $user, string $password) use ($verifyEmail): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            if ($verifyEmail && ! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, __($status));

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function setupOtpReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);
        $user = User::query()->where('email', Str::lower($validated['email']))->first();
        abort_unless($user !== null && $user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');
        abort_unless(Password::broker()->tokenExists($user, $validated['token']), 422, 'The reset token is invalid or expired.');
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $challengeId = (string) Str::uuid();
        $userChallengeKey = 'public-otp-reset-user:'.$user->id;
        $previousChallengeId = cache()->pull($userChallengeKey);
        if (is_string($previousChallengeId)) {
            cache()->forget('public-otp-reset:'.$previousChallengeId);
        }
        cache()->put('public-otp-reset:'.$challengeId, [
            'user_id' => $user->id,
            'token' => $validated['token'],
            'secret' => $secret,
        ], now()->addMinutes(10));
        cache()->put($userChallengeKey, $challengeId, now()->addMinutes(10));

        return response()->json(['data' => [
            'challenge_id' => $challengeId,
            'secret' => $secret,
            'provisioning_uri' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ]], 201)->header('Cache-Control', 'no-store');
    }

    public function confirmOtpReset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);
        $challengeKey = 'public-otp-reset:'.$validated['challenge_id'];
        $challenge = cache()->get($challengeKey);
        abort_unless(is_array($challenge), 422, 'The reset challenge is invalid or expired.');
        $user = User::query()->findOrFail($challenge['user_id']);
        abort_unless($user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');
        abort_unless(Password::broker()->tokenExists($user, $challenge['token']), 422, 'The reset token is invalid or expired.');
        abort_unless((new Google2FA)->verifyKey($challenge['secret'], $validated['code']), 422, 'The OTP code is invalid.');
        cache()->forget($challengeKey);
        $codes = collect(range(1, 8))->map(fn (): string => strtoupper(bin2hex(random_bytes(4))))->all();
        $user->update([
            'two_factor_secret' => encrypt($challenge['secret']),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR)),
        ]);
        Password::broker()->deleteToken($user);
        cache()->forget('public-otp-reset-user:'.$user->id);

        return response()->json(['data' => ['recovery_codes' => $codes]])
            ->header('Cache-Control', 'no-store');
    }

    public function recoveryCodeLogin(Request $request): JsonResponse
    {
        $request->validate(['recovery_code' => ['required', 'string']]);
        $user = $this->authenticatedUser($request);
        abort_unless($user->currentAccessToken()?->can('otp-verify'), 401, 'The temporary token is invalid or expired.');
        abort_unless($user->canAccessApplication(), 403, 'Tu cuenta no está aprobada.');
        $codes = $user->two_factor_recovery_codes === null
            ? []
            : json_decode(decrypt($user->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR);
        $index = collect($codes)->search(fn (string $code): bool => hash_equals($code, Str::upper($request->string('recovery_code')->toString())));
        abort_if($index === false, 422, 'The recovery code is invalid.');
        unset($codes[$index]);
        $user->update(['two_factor_recovery_codes' => encrypt(json_encode(array_values($codes), JSON_THROW_ON_ERROR))]);
        $user->tokens()->where('name', 'temp-otp')->delete();
        $token = $user->createToken('auth-token');

        return response()->json(['data' => ['token' => $token->plainTextToken, 'user' => new UserResource($user)]])
            ->header('Cache-Control', 'no-store');
    }

    public function confirmEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'hash' => ['required', 'string'],
        ]);
        $user = User::query()->findOrFail($validated['id']);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $validated['hash']), 403, 'The verification token is invalid.');
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['message' => 'Email verified.']);
    }

    private function sendRecoveryNotification(string $email, string $path): void
    {
        $user = User::query()->where('email', Str::lower($email))->first();

        if ($user === null) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $url = rtrim((string) config('app.mobile_url'), '/').'/'.$path.'?'.http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);
        $user->notify(new ResetPasswordNotification($user, $url));
    }
}
