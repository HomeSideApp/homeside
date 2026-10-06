<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteGoogleIdentityRequest;
use App\Http\Requests\LinkGoogleIdentityRequest;
use App\Services\Auth\GoogleIdentityService;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Support\SecurityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MeGoogleIdentityController extends Controller
{
    public function store(LinkGoogleIdentityRequest $request, GoogleIdTokenVerifier $verifier, GoogleIdentityService $identities): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $google = $verifier->verify($validated['id_token']);
        $identities->link($user, $google['sub'], $google['email'], $google['email_verified']);
        SecurityConfirmation::consume($request);

        return response()->json(['data' => ['google_connected' => true]]);
    }

    public function destroy(DeleteGoogleIdentityRequest $request): Response
    {
        $user = $this->authenticatedUser($request);
        $user->googleIdentity()->delete();
        SecurityConfirmation::consume($request);

        return response()->noContent();
    }
}
