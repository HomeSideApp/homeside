<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ApiLogin;
use App\Actions\Auth\ApiLoginOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles API authentication (login + OTP).
 */
#[Group(name: 'Authentication', description: 'Login, logout, token refresh, and OTP management.')]
final class LoginController extends Controller
{
    /**
     * Authenticate with email and password, returning a temp OTP token.
     *
     * The returned `temp_token` must be used within 5 minutes to call
     * `POST /auth/login/otp` with the 6-digit code from the authenticator app.
     *
     * @param  LoginRequest  $request  The incoming HTTP request.
     * @param  ApiLogin  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    #[Endpoint(title: 'Login (step 1)', description: 'Submit email and password to receive a temporary OTP token. The temp_token expires in 5 minutes.')]
    #[Response(status: 200, description: 'OTP required. A temporary token is returned.')]
    #[Response(status: 401, description: 'Invalid email or password.')]
    #[Response(status: 403, description: 'Two-factor authentication is not configured on this account.')]
    #[Response(status: 422, description: 'Validation error (missing email or password).')]
    public function login(LoginRequest $request, ApiLogin $action): JsonResponse
    {
        $result = $action->execute(
            $request->validated('email'),
            $request->validated('password')
        );

        return response()->json([
            'message' => __('app.errors.otp_required'),
            'temp_token' => $result['temp_token'],
        ]);
    }

    /**
     * Verify the OTP code and issue a long-lived auth token.
     *
     * The returned `token` expires in 30 days (configurable via
     * SANCTUM_TOKEN_EXPIRATION_MINUTES). Use `POST /auth/refresh` to rotate
     * before expiry.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  ApiLoginOtp  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    #[Endpoint(title: 'Login (step 2 — OTP)', description: 'Verify the 6-digit OTP code from the authenticator app. Returns a long-lived auth token (30 days).')]
    #[Response(status: 200, description: 'Authentication successful. Returns the auth token and basic user info.')]
    #[Response(status: 401, description: 'Invalid or expired temp token.')]
    #[Response(status: 422, description: 'Invalid OTP code or OTP not configured.')]
    public function loginOtp(Request $request, ApiLoginOtp $action): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (! $user || ! $user->currentAccessToken()->can('otp-verify')) {
            abort(401, __('app.errors.token_temporal_invalid'));
        }

        $result = $action->execute($user, $request->input('code'));

        return response()->json([
            'message' => __('app.errors.auth_success'),
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }
}
