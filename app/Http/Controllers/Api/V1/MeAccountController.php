<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\Users\UserResource;
use App\Notifications\MobileVerifyEmailNotification;
use App\Support\SecurityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MeAccountController extends Controller
{
    public function updateProfile(ProfileUpdateRequest $request): UserResource
    {
        $user = $this->authenticatedUser($request);
        $emailChanged = $user->email !== $request->validated('email');
        $user->fill($request->validated());

        if (! $user->households_enabled) {
            $user->active_household_id = null;
        }

        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return new UserResource($user->fresh());
    }

    public function updatePassword(PasswordUpdateRequest $request): Response
    {
        $user = $this->authenticatedUser($request);
        $user->update(['password' => $request->validated('password')]);
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        return response()->noContent();
    }

    public function confirmSecurity(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        return response()->json(['data' => SecurityConfirmation::issue($this->authenticatedUser($request))], 201);
    }

    public function sendVerification(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        if (! $user->hasVerifiedEmail()) {
            $user->notify(new MobileVerifyEmailNotification);
        }

        return response()->json(['message' => 'If verification is required, a message has been sent.'], 202);
    }

    public function destroy(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        SecurityConfirmation::authorize($request, $user);
        $user->tokens()->delete();
        $user->delete();
        SecurityConfirmation::consume($request);

        return response()->noContent();
    }
}
