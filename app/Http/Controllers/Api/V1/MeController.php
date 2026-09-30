<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Returns the authenticated user's profile, including roles and permissions.
 */
final class MeController extends Controller
{
    /**
     * Show the authenticated user with their roles and permission route names.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return JsonResource The user resource response.
     */
    public function show(Request $request): JsonResource
    {
        return UserResource::make($this->authenticatedUser($request));
    }
}
