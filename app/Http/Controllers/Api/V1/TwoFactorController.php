<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json(['data' => [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toISOString(),
        ]]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        abort_if($user->hasEnabledTwoFactorAuthentication(), 409, 'Two-factor authentication is already enabled.');

        return $this->createChallenge($user, 'setup');
    }

    public function reset(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        $response = $this->createChallenge($user, 'reset');
        SecurityConfirmation::consume($request);

        return $response;
    }

    public function confirm(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);
        $challenge = Cache::get($this->challengeKey($validated['challenge_id']));
        abort_unless(is_array($challenge) && hash_equals($user->id, (string) ($challenge['user_id'] ?? '')), 422, 'The challenge is invalid or expired.');
        $expectedType = str_ends_with((string) $request->route()?->getName(), 'reset.confirm') ? 'reset' : 'setup';
        abort_unless(($challenge['type'] ?? null) === $expectedType, 422, 'The challenge does not match this operation.');
        abort_unless((new Google2FA)->verifyKey($challenge['secret'], $validated['code']), 422, 'The OTP code is invalid.');

        $recoveryCodes = $this->newRecoveryCodes();
        $user->update([
            'two_factor_secret' => encrypt($challenge['secret']),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes, JSON_THROW_ON_ERROR)),
        ]);
        Cache::forget($this->challengeKey($validated['challenge_id']));
        Cache::forget('two-factor-challenge-user:'.$user->id);

        return response()->json(['data' => [
            'enabled' => true,
            'recovery_codes' => $recoveryCodes,
        ]])->header('Cache-Control', 'no-store');
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        $codes = $this->storedRecoveryCodes($user);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['recovery_codes' => $codes]])
            ->header('Cache-Control', 'no-store');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 409, 'Two-factor authentication is not enabled.');
        $codes = $this->newRecoveryCodes();
        $user->update(['two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR))]);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['recovery_codes' => $codes]], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);
        $challengeId = Cache::pull('two-factor-challenge-user:'.$user->id);
        if (is_string($challengeId)) {
            Cache::forget($this->challengeKey($challengeId));
        }
        SecurityConfirmation::consume($request);

        return response()->noContent();
    }

    private function createChallenge(User $user, string $type): JsonResponse
    {
        $previousChallengeId = Cache::pull('two-factor-challenge-user:'.$user->id);
        if (is_string($previousChallengeId)) {
            Cache::forget($this->challengeKey($previousChallengeId));
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $challengeId = (string) Str::uuid();
        Cache::put($this->challengeKey($challengeId), [
            'user_id' => $user->id,
            'secret' => $secret,
            'type' => $type,
        ], now()->addMinutes(10));
        Cache::put('two-factor-challenge-user:'.$user->id, $challengeId, now()->addMinutes(10));

        return response()->json(['data' => [
            'challenge_id' => $challengeId,
            'secret' => $secret,
            'provisioning_uri' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ]], 201)->header('Cache-Control', 'no-store');
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn (): string => strtoupper(bin2hex(random_bytes(4))))->all();
    }

    /** @return list<string> */
    private function storedRecoveryCodes(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR);
    }

    private function challengeKey(string $challengeId): string
    {
        return 'two-factor-challenge:'.$challengeId;
    }
}
