<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\RegisterViaInviteLink;
use App\Data\Households\RegisterViaInviteLinkData;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterViaInviteLinkRequest;
use App\Http\Resources\Households\HouseholdInviteLinkResource;
use App\Models\HouseholdInviteLink;
use Illuminate\Http\JsonResponse;

/**
 * Handles the public API for registering through household invite links.
 */
final class InviteLinkController extends Controller
{
    /**
     * Show the invite link payload with its household.
     *
     * @param  string  $token  The public invite link token.
     * @return JsonResponse The HTTP response.
     */
    public function show(string $token): JsonResponse
    {
        $link = HouseholdInviteLink::query()->where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 404, __('app.errors.invite_link_unusable'));

        $link->load('household:id,name,image_url');

        return new JsonResponse(['data' => new HouseholdInviteLinkResource($link)]);
    }

    /**
     * Register a new verified user through an invite link.
     *
     * @param  RegisterViaInviteLinkRequest  $request  The incoming HTTP request.
     * @param  string  $token  The public invite link token.
     * @param  RegisterViaInviteLink  $action  The action responsible for the operation.
     * @return JsonResponse The HTTP response.
     */
    public function store(RegisterViaInviteLinkRequest $request, string $token, RegisterViaInviteLink $action): JsonResponse
    {
        $link = HouseholdInviteLink::query()->where('token', $token)->firstOrFail();
        abort_unless($request->validated()['token'] === $token, 422, __('app.errors.invite_link_unusable'));

        $user = $action->execute(RegisterViaInviteLinkData::fromArray($request->validated()), $link);

        return new JsonResponse([
            'data' => [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'household' => [
                    'id' => $link->household_id,
                    'name' => $link->household?->name,
                ],
            ],
        ], 201);
    }
}
