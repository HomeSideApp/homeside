<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class GoogleAuthController extends Controller
{
    public function login(Request $request, GoogleIdTokenVerifier $verifier, GoogleIdentityService $identities): JsonResponse
    {
        $input = $request->validate(['id_token' => ['required', 'string', 'max:8192']]);
        $google = $verifier->verify($input['id_token']);
        $result = $identities->resolve($google['sub'], $google['email'], $google['name'], $google['email_verified']);

        if ($result['status'] === 'link_required') {
            return response()->json(['code' => 'link_required', 'message' => 'Entra con tu método actual y vincula Google.'], 409);
        }
        if ($result['status'] === 'pending') {
            return response()->json(['code' => 'approval_pending', 'message' => 'Tu solicitud está pendiente de aprobación.'], 202);
        }
        if ($result['status'] === 'rejected') {
            return response()->json(['code' => 'approval_rejected', 'message' => 'Tu solicitud fue rechazada.'], 403);
        }

        $user = $result['user'];
        abort_unless($user !== null && $user->canAccessApplication(), 403, 'Tu cuenta no tiene un rol asignado.');
        $user->tokens()->whereIn('name', ['temp-otp', 'google-otp-setup'])->delete();
        $name = $user->hasEnabledTwoFactorAuthentication() ? 'temp-otp' : 'google-otp-setup';
        $ability = $name === 'temp-otp' ? 'otp-verify' : 'otp-setup';
        $token = $user->createToken($name, [$ability], now()->addMinutes(5));

        return response()->json([
            'requires_otp' => $name === 'temp-otp',
            'requires_otp_setup' => $name === 'google-otp-setup',
            'temp_token' => $token->plainTextToken,
        ])->header('Cache-Control', 'no-store');
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $this->setupUser($request);
        $challengeId = (string) Str::uuid();
        $secret = (new Google2FA)->generateSecretKey();
        Cache::put('google-otp-setup:'.$challengeId, ['user_id' => $user->id, 'secret' => $secret], now()->addMinutes(5));

        return response()->json(['data' => [
            'challenge_id' => $challengeId,
            'secret' => $secret,
            'provisioning_uri' => (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ]])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request): JsonResponse
    {
        $user = $this->setupUser($request);
        $validated = $request->validate(['challenge_id' => ['required', 'uuid'], 'code' => ['required', 'digits:6']]);
        $key = 'google-otp-setup:'.$validated['challenge_id'];
        $challenge = Cache::get($key);
        abort_unless(is_array($challenge) && hash_equals($user->id, (string) ($challenge['user_id'] ?? '')), 422, 'Desafío caducado.');
        abort_unless((bool) (new Google2FA)->verifyKey($challenge['secret'], $validated['code']), 422, 'Código OTP incorrecto.');

        $codes = collect(range(1, 8))->map(fn (): string => strtoupper(bin2hex(random_bytes(4))))->all();
        $user->update([
            'two_factor_secret' => encrypt($challenge['secret']),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR)),
        ]);
        Cache::forget($key);
        $user->currentAccessToken()->delete();
        $token = $user->createToken('auth-token');

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'recovery_codes' => $codes,
        ]])->header('Cache-Control', 'no-store');
    }

    private function setupUser(Request $request): User
    {
        $user = $this->authenticatedUser($request);
        abort_unless($user->canAccessApplication()
            && $user->currentAccessToken()->name === 'google-otp-setup'
            && $user->currentAccessToken()->can('otp-setup')
            && ! $user->hasEnabledTwoFactorAuthentication(), 403);

        return $user;
    }
}
