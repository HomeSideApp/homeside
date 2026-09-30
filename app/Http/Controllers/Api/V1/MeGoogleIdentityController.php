<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Support\SecurityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeGoogleIdentityController extends Controller
{
    public function store(Request $request, GoogleIdTokenVerifier $verifier, GoogleIdentityService $identities): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        $validated = $request->validate(['id_token' => ['required', 'string', 'max:8192']]);
        $google = $verifier->verify($validated['id_token']);
        $identities->link($user, $google['sub'], $google['email'], $google['email_verified']);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['google_connected' => true]]);
    }

    public function destroy(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        abort_if($user->googleIdentity()->doesntExist(), 404);
        $user->googleIdentity()->delete();
        SecurityConfirmation::consume($request);

        return response()->noContent();
    }
}
