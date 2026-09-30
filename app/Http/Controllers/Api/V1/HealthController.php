<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight health-check endpoint.
 *
 * Intended for clients to verify connectivity before sending credentials.
 * No authentication required.
 */
#[Group(name: 'Health', description: 'Public health-check endpoints. No authentication required.')]
final class HealthController extends Controller
{
    /**
     * Return basic application health information.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return JsonResponse Health status.
     */
    #[Endpoint(title: 'Health check', description: 'Verify API connectivity and availability. Returns the application name and environment. No authentication required — ideal for pre-login connectivity checks.')]
    #[Response(status: 200, description: 'Application is healthy. Returns app name and environment.')]
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => config('app.env'),
        ]);
    }
}
