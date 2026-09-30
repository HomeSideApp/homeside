<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ApiRefreshToken;
use App\Actions\Auth\ApiResendOtp;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Handles token rotation (refresh) and OTP resending.
 */
#[Group(name: 'Authentication', description: 'Login, logout, token refresh, and OTP management.')]
final class RefreshController extends Controller
{
    /**
     * Rotate the current auth token: revoke it and issue a fresh one.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  ApiRefreshToken  $action  The action responsible for the operation.
     * @return JsonResponse The new token.
     */
    #[Endpoint(title: 'Refresh token', description: 'Rotate the current auth token. The old token is revoked and a fresh 30-day token is issued. Call this before the current token expires to maintain continuous access.')]
    #[Response(status: 200, description: 'Token rotated successfully. Returns the new token and basic user info.')]
    #[Response(status: 401, description: 'No valid token provided or token has expired. Client must re-authenticate.')]
    public function refresh(Request $request, ApiRefreshToken $action): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            abort(401, __('app.errors.token_invalid'));
        }

        $result = $action->execute($this->authenticatedUser($request), $bearerToken);

        return response()->json([
            'message' => __('app.errors.auth_success'),
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    /**
     * Renew the temporary OTP token so the user has more time to enter the code.
     *
     * Requires a valid temp-otp token (not an auth-token).
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  ApiResendOtp  $action  The action responsible for the operation.
     * @return JsonResponse A new temp_token.
     */
    #[Endpoint(title: 'Resend OTP code', description: 'Renew the temporary OTP token with a fresh 5-minute TTL. Use this when the previous temp_token is about to expire and the user needs more time to enter the OTP code. Requires the current temp-otp token (not an auth-token).')]
    #[Response(status: 200, description: 'Temp token renewed. A fresh temp_token with 5-minute TTL is returned.')]
    #[Response(status: 401, description: 'No valid temp token provided or token has expired. Client must re-authenticate.')]
    public function resendOtp(Request $request, ApiResendOtp $action): JsonResponse
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            abort(401, __('app.errors.token_temporal_invalid'));
        }

        // Extract token ID from bearer format: "id|random|hash"
        $tokenParts = explode('|', $bearerToken);
        $tokenId = $tokenParts[0] ?? null;

        $accessToken = $tokenId !== null
            ? PersonalAccessToken::find($tokenId)
            : null;

        if ($accessToken === null || $accessToken->name !== 'temp-otp') {
            abort(401, __('app.errors.token_temporal_invalid'));
        }

        $result = $action->execute($request->user());

        return response()->json([
            'message' => __('app.errors.otp_required'),
            'temp_token' => $result['temp_token'],
        ]);
    }
}
