<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Handles API logout.
 */
#[Group(name: 'Authentication', description: 'Login, logout, token refresh, and OTP management.')]
final class LogoutController extends Controller
{
    /**
     * Revoke the current API token.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    #[Endpoint(title: 'Logout', description: 'Revoke the current auth token. The client must discard the token after this call.')]
    #[ApiResponse(status: 204, description: 'Token revoked successfully.')]
    #[ApiResponse(status: 401, description: 'No valid token provided.')]
    public function destroy(Request $request): Response
    {
        $this->authenticatedUser($request)->currentAccessToken()->delete();

        return response()->noContent();
    }
}
