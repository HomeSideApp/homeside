<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmGoogleOtpApiRequest;
use App\Http\Requests\GoogleIdTokenRequest;
use App\Http\Requests\GoogleOtpSetupRequest;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class GoogleAuthController extends Controller
{
    public function login(GoogleIdTokenRequest $request, GoogleIdTokenVerifier $verifier, GoogleIdentityService $identities): JsonResponse
    {
        $input = $request->validated();
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
        $user = $request->authorizeAccount($user);
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

    public function setup(GoogleOtpSetupRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $challengeId = (string) Str::uuid();
        $secret = (new Google2FA)->generateSecretKey();
        Cache::put('google-otp-setup:'.$challengeId, ['user_id' => $user->id, 'secret' => $secret], now()->addMinutes(5));

        return response()->json(['data' => [
            'challenge_id' => $challengeId,
            'secret' => $secret,
            'provisioning_uri' => (new Google2FA)->getQRCodeUrl(config('app.name'), $user->email, $secret),
        ]])->header('Cache-Control', 'no-store');
    }

    public function confirm(ConfirmGoogleOtpApiRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $key = 'google-otp-setup:'.$validated['challenge_id'];
        $challenge = $request->challenge();

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
}
