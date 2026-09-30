<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for the application.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;

    /**
     * Return the authenticated user, aborting with 401 when absent.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return User The User value.
     */
    protected function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
