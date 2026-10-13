<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\RegisterAccountData;
use App\Enums\AppLocale;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmEmailRequest;
use App\Http\Requests\ConfirmOtpResetRequest;
use App\Http\Requests\RecoveryCodeLoginRequest;
use App\Http\Requests\RecoveryEmailRequest;
use App\Http\Requests\RegisterAccountApiRequest;
use App\Http\Requests\ResetAccountPasswordRequest;
use App\Http\Requests\SetupOtpResetRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class PublicAccountController extends Controller
{
    /**
     * Register a new account through the shared registration use case.
     *
     * Contract:
     * - `denied` (the secure API default): 403 `{code, message}` and nothing is
     *   created or mailed.
     * - `approval_required`: 201 with `state = approval_pending`, the user is
     *   created as `pending`, and NO authentication token is issued.
     * - `allowed`: 201 with the user resource.
     *
     * Both creating decisions also return a single-use `enrolment` bootstrap
     * (`token` + `expires_at`) so the client can complete the two-factor step
     * without a session. The token is the only credential handed out here; the
     * enrolment SECRET is never delivered by this endpoint, only by the
     * bootstrap-authenticated setup exchange. A token is never returned for an
     * account that is not approved.
     */
    public function register(
        RegisterAccountApiRequest $request,
        RegisterAccount $accounts,
        CreateAuthenticationBootstrap $bootstraps,
    ): JsonResponse {
        $validated = $request->validated();

        $outcome = $accounts->execute(new RegisterAccountData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            locale: $validated['locale'] ?? AppLocale::default()->value,
            channel: RegistrationChannel::Api,
            origin: 'api',
        ));

        if ($outcome->decision === RegistrationDecision::ExistingIdentity) {
            return response()->json([
                'code' => 'identity_exists',
                'message' => 'An account already exists for this email. Authenticate to continue.',
            ], 409);
        }

        if (! $outcome->registered() || $outcome->user === null) {
            return response()->json([
                'code' => $outcome->reason ?? 'registration_disabled',
                'message' => 'Registration is not available on this channel.',
            ], 403);
        }

        $resource = UserResource::make($outcome->user);

        $additional = [
            'state' => $outcome->decision === RegistrationDecision::ApprovalRequired
                ? 'approval_pending'
                : $outcome->nextStep,
        ];

        if (! $outcome->user->hasEnabledTwoFactorAuthentication()) {
            $token = $bootstraps->execute($outcome->user, AuthenticationBootstrapPurpose::TwoFactorEnrolment);
            $additional['enrolment'] = [
                'token' => $token,
                'expires_at' => now()->addMinutes(CreateAuthenticationBootstrap::TTL_MINUTES)->toISOString(),
            ];
        }

        return $resource->additional($additional)->response()->setStatusCode(201);
    }

    public function forgotPassword(RecoveryEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->sendRecoveryNotification($validated['email'], 'password/reset');

        return response()->json(['message' => 'If the account exists, recovery instructions have been sent.'], 202);
    }

    public function resetPassword(ResetAccountPasswordRequest $request): JsonResponse
    {
        return $this->completePasswordReset($request, false);
    }

    public function forgotOtp(RecoveryEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->sendRecoveryNotification($validated['email'], 'otp/reset');

        return response()->json(['message' => 'If the account exists, recovery instructions have been sent.'], 202);
    }

    public function setPassword(ResetAccountPasswordRequest $request): JsonResponse
    {
        return $this->completePasswordReset($request, true);
    }

    private function completePasswordReset(ResetAccountPasswordRequest $request, bool $verifyEmail): JsonResponse
    {
        $validated = $request->validated();
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

    public function setupOtpReset(SetupOtpResetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->resetUser();
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

    public function confirmOtpReset(ConfirmOtpResetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $challengeKey = 'public-otp-reset:'.$validated['challenge_id'];
        $challenge = $request->challenge();
        $user = $request->resetUser();
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

    public function recoveryCodeLogin(RecoveryCodeLoginRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $codes = $request->remainingCodes();
        $user->update(['two_factor_recovery_codes' => encrypt(json_encode($codes, JSON_THROW_ON_ERROR))]);
        $user->tokens()->where('name', 'temp-otp')->delete();
        $token = $user->createToken('auth-token');

        return response()->json(['data' => ['token' => $token->plainTextToken, 'user' => new UserResource($user)]])
            ->header('Cache-Control', 'no-store');
    }

    public function confirmEmail(ConfirmEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->verificationUser();
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
