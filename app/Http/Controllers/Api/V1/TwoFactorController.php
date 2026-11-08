<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\CompleteAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\ApprovalStatus;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmTwoFactorRequest;
use App\Http\Requests\RegenerateRecoveryCodesRequest;
use App\Http\Requests\SecurityConfirmedRequest;
use App\Http\Requests\SetupTwoFactorRequest;
use App\Http\Resources\Users\UserResource;
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

    public function setup(SetupTwoFactorRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return $this->createChallenge($user, 'setup');
    }

    /**
     * Exchange a single-use enrolment bootstrap for the enrolment secret.
     *
     * This is the bootstrap-authenticated step the contract asks for: the
     * secret is delivered in the RESPONSE BODY, never in a URL, and only to the
     * client that holds the unconsumed enrolment token. The token itself is
     * carried in the request body; a client never receives it from a link.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  AuthenticationBootstrapRepository  $bootstraps  The bootstrap repository.
     * @return JsonResponse The JSON response.
     */
    public function setupFromBootstrap(Request $request, AuthenticationBootstrapRepository $bootstraps): JsonResponse
    {
        $validated = $request->validate([
            'enrolment_token' => ['required', 'string'],
        ]);

        $bootstrap = $bootstraps->findActive($validated['enrolment_token'], AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        if ($bootstrap === null || $bootstrap->user === null) {
            return $this->invalidBootstrap();
        }

        $user = $bootstrap->user;

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return response()->json([
                'code' => 'two_factor_already_enabled',
                'message' => __('app.errors.two_factor_already_enabled'),
            ], 409);
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $challengeId = (string) Str::uuid();

        Cache::put($this->bootstrapChallengeKey($challengeId), [
            'bootstrap_id' => $bootstrap->id,
            'user_id' => $user->id,
            'secret' => $secret,
        ], now()->addMinutes(10));

        return response()->json(['data' => [
            'challenge_id' => $challengeId,
            'secret' => $secret,
            'provisioning_uri' => $google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret),
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ]], 201)->header('Cache-Control', 'no-store');
    }

    /**
     * Verify the code, consume the bootstrap and issue the auth token.
     *
     * A wrong code never consumes the grant, so a retry within the TTL still
     * works. Completion always persists the second factor, but the long-lived
     * token is issued ONLY when approval, email verification and account status
     * all allow a session; otherwise the client is told which step is missing.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  AuthenticationBootstrapRepository  $bootstraps  The bootstrap repository.
     * @param  CompleteAuthenticationBootstrap  $complete  The redemption action.
     * @return JsonResponse The JSON response.
     */
    public function confirmFromBootstrap(
        Request $request,
        AuthenticationBootstrapRepository $bootstraps,
        CompleteAuthenticationBootstrap $complete,
    ): JsonResponse {
        $validated = $request->validate([
            'enrolment_token' => ['required', 'string'],
            'challenge_id' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $challengeKey = $this->bootstrapChallengeKey($validated['challenge_id']);
        $challenge = Cache::pull($challengeKey);
        $bootstrap = $bootstraps->findActive($validated['enrolment_token'], AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        if (! is_array($challenge)
            || $bootstrap === null
            || ($challenge['bootstrap_id'] ?? null) !== $bootstrap->id) {
            return response()->json([
                'code' => 'enrolment_challenge_expired',
                'message' => __('app.errors.session_expired'),
            ], 422);
        }

        $consumed = $complete->execute(
            $validated['enrolment_token'],
            AuthenticationBootstrapPurpose::TwoFactorEnrolment,
            (string) $challenge['secret'],
            $validated['code'],
        );

        if ($consumed === null) {
            // Wrong code: restore the challenge so the client can retry inside
            // its TTL without consuming the single-use token.
            Cache::put($challengeKey, $challenge, now()->addMinutes(10));

            return response()->json([
                'code' => 'otp_code_invalid',
                'message' => __('app.errors.otp_code_invalid_verify'),
            ], 422);
        }

        $user = $consumed->user;

        if ($user === null || ! $consumed->mayEstablishSession()) {
            return response()->json([
                'code' => 'account_not_ready',
                'message' => __('app.errors.account_not_ready'),
                'state' => $user === null ? 'account_unavailable' : $this->blockingState($user),
                'user' => $user === null ? null : UserResource::make($user)->resolve(),
            ], 202);
        }

        $token = $user->createToken('auth-token');

        return response()->json([
            'message' => __('app.errors.auth_success'),
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user)->resolve(),
        ])->header('Cache-Control', 'no-store');
    }

    public function reset(SecurityConfirmedRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $response = $this->createChallenge($user, 'reset');
        SecurityConfirmation::consume($request);

        return $response;
    }

    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $challenge = $request->challenge();

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

    public function recoveryCodes(SecurityConfirmedRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $codes = $this->storedRecoveryCodes($user);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['recovery_codes' => $codes]])
            ->header('Cache-Control', 'no-store');
    }

    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $codes = $this->newRecoveryCodes();
        $user->update(['two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR))]);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['recovery_codes' => $codes]], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function destroy(SecurityConfirmedRequest $request): Response
    {
        $user = $this->authenticatedUser($request);
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

    /**
     * Build the cache key for a bootstrap enrolment challenge.
     *
     * @param  string  $challengeId  The challenge identifier.
     * @return string The cache key.
     */
    private function bootstrapChallengeKey(string $challengeId): string
    {
        return 'bootstrap-two-factor-challenge:'.$challengeId;
    }

    /**
     * Fail closed for a missing, expired or consumed enrolment token.
     *
     * The message never says whether a user exists.
     *
     * @return JsonResponse The JSON response.
     */
    private function invalidBootstrap(): JsonResponse
    {
        return response()->json([
            'code' => 'enrolment_token_invalid',
            'message' => __('app.errors.bootstrap_invalid'),
        ], 401)->header('Cache-Control', 'no-store');
    }

    /**
     * Describe why an otherwise complete account may not be signed in yet.
     *
     * @param  User  $user  The account that just completed enrolment.
     * @return string One of `approval_pending`, `verify_email`, `account_unavailable`.
     */
    private function blockingState(User $user): string
    {
        if ($user->approval_status === ApprovalStatus::Pending->value) {
            return 'approval_pending';
        }

        if (! $user->hasVerifiedEmail()) {
            return 'verify_email';
        }

        return 'account_unavailable';
    }
}
