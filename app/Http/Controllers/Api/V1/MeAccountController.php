<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmAccountSecurityRequest;
use App\Http\Requests\SecurityConfirmedRequest;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Resources\Users\UserResource;
use App\Notifications\MobileVerifyEmailNotification;
use App\Support\SecurityConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class MeAccountController extends Controller
{
    public function updateProfile(ProfileUpdateRequest $request): UserResource
    {
        $user = $this->authenticatedUser($request);
        $emailChanged = $user->email !== $request->validated('email');

        // `allow_cloud_ai` no es una columna: se traduce a la fecha del permiso.
        // Así queda cuándo lo dio, y revocarlo es ponerla en null.
        // Un form HTML manda "1"/"0" y la API un booleano: `boolean()` entiende
        // los dos, así el interruptor y el JSON se comportan igual.
        $consent = $request->has('allow_cloud_ai') ? $request->boolean('allow_cloud_ai') : null;
        $attributes = $request->safe()->except('allow_cloud_ai');
        $user->fill($attributes);

        if ($consent !== null) {
            $user->ai_receipt_consent_at = $consent ? ($user->ai_receipt_consent_at ?? now()) : null;
        }

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

    public function confirmSecurity(ConfirmAccountSecurityRequest $request): JsonResponse
    {

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

    public function destroy(SecurityConfirmedRequest $request): Response
    {
        $user = $this->authenticatedUser($request);
        DB::transaction(function () use ($user): void {
            $user->delete();
            $user->tokens()->delete();
        }, 3);
        SecurityConfirmation::consume($request);

        return response()->noContent();
    }
}
