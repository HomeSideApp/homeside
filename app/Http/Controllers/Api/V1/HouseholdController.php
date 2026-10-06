<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\AcceptHouseholdInvitation;
use App\Actions\Households\AcceptInvitation;
use App\Actions\Households\CancelInvitation;
use App\Actions\Households\CreateHousehold;
use App\Actions\Households\CreateHouseholdInviteLink;
use App\Actions\Households\DeleteHousehold;
use App\Actions\Households\GetHousehold;
use App\Actions\Households\InviteMember;
use App\Actions\Households\ListHouseholds;
use App\Actions\Households\RemoveMember;
use App\Actions\Households\RevokeHouseholdInviteLink;
use App\Actions\Households\SwitchActiveHousehold;
use App\Actions\Households\UpdateHouseholdSettings;
use App\Data\Households\CreateHouseholdData;
use App\Data\Households\CreateInviteLinkData;
use App\Data\Households\InviteMemberData;
use App\Data\Households\UpdateHouseholdSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptHouseholdInvitationByTokenRequest;
use App\Http\Requests\CreateHouseholdInviteLinkRequest;
use App\Http\Requests\DeleteHouseholdRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\LinkHouseholdMemberContactRequest;
use App\Http\Requests\RemoveHouseholdMemberRequest;
use App\Http\Requests\RevokeHouseholdInviteLinkRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdSettingsRequest;
use App\Http\Requests\ViewHouseholdRequest;
use App\Http\Resources\Households\HouseholdInvitationResource;
use App\Http\Resources\Households\HouseholdInviteLinkResource;
use App\Http\Resources\Households\HouseholdMemberResource;
use App\Http\Resources\Households\HouseholdResource;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdInviteLink;
use App\Models\HouseholdMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class HouseholdController
 *
 * This controller handles the API endpoints for managing households, including listing,
 * creating, viewing, updating, inviting members, switching and deleting households.
 */
final class HouseholdController extends Controller
{
    /**
     * List the households of the authenticated user.
     */
    public function index(ListHouseholds $action, Request $request): AnonymousResourceCollection
    {
        $households = $action->execute($this->authenticatedUser($request));

        return HouseholdResource::collection($households);
    }

    /**
     * Store a newly created household.
     */
    public function store(
        StoreHouseholdRequest $request,
        CreateHousehold $action,
        UpdateHouseholdSettings $settingsAction,
    ): JsonResponse {
        $validated = $request->validated();
        $household = $action->execute(
            CreateHouseholdData::fromArray($validated),
            $this->authenticatedUser($request),
        );
        $warnings = [];

        if ($request->hasFile('image') || $request->has('modules') || $request->has('tags')) {
            $result = $settingsAction->execute($household, new UpdateHouseholdSettingsData(
                name: $validated['name'],
                description: $validated['description'] ?? null,
                color: $validated['color'] ?? null,
                image: $request->file('image'),
                removeImage: (bool) ($validated['remove_image'] ?? false),
                modules: $validated['modules'] ?? null,
                tags: $validated['tags'] ?? null,
            ));
            $household = $result['household'];
            $warnings = $result['warnings'];
        }

        $household->loadMissing('members');

        return HouseholdResource::make($household)
            ->additional(['warnings' => $warnings])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Return the given household.
     */
    public function show(Household $household, GetHousehold $action, ViewHouseholdRequest $request): HouseholdResource
    {

        return new HouseholdResource($action->execute($household));
    }

    /**
     * Update the given household settings.
     */
    public function update(
        UpdateHouseholdSettingsRequest $request,
        Household $household,
        UpdateHouseholdSettings $action,
    ): HouseholdResource {
        $validated = $request->validated();
        $result = $action->execute($household, new UpdateHouseholdSettingsData(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            color: $validated['color'] ?? null,
            image: $request->file('image'),
            removeImage: (bool) ($validated['remove_image'] ?? false),
            modules: $validated['modules'] ?? null,
            tags: $validated['tags'] ?? null,
            defaultSplitType: $validated['default_split_type'] ?? null,
        ));

        return (new HouseholdResource($result['household']))
            ->additional(['warnings' => $result['warnings']]);
    }

    /**
     * Delete the given household.
     */
    public function destroy(Household $household, DeleteHousehold $action, DeleteHouseholdRequest $request): Response
    {
        $action->execute($household);

        return response()->noContent();
    }

    /**
     * Invite a member to the given household.
     */
    public function invite(InviteMemberRequest $request, Household $household, InviteMember $action): JsonResponse
    {
        $data = InviteMemberData::fromArray($request->validated());
        $invitation = $action->execute($data, $household, $this->authenticatedUser($request));

        return response()->json([
            'data' => (new HouseholdInvitationResource($invitation->load(['household', 'inviter'])))->resolve($request),
            'message' => __('app.toast.invitation_sent'),
        ], 201);
    }

    /**
     * Accept a household invitation.
     */
    public function acceptInvitation(AcceptHouseholdInvitationByTokenRequest $request, AcceptInvitation $action): HouseholdInvitationResource
    {
        $token = $request->validated('token');
        $invitation = HouseholdInvitation::query()->where('token', $token)->firstOrFail();
        $action->execute($token, $this->authenticatedUser($request));

        return new HouseholdInvitationResource($invitation->fresh()->load(['household', 'inviter']));
    }

    /**
     * Accept a household invitation from the invitation list.
     */
    public function acceptFromList(HouseholdInvitation $invitation, AcceptHouseholdInvitation $action, Request $request): HouseholdInvitationResource
    {
        $action->execute($invitation, $this->authenticatedUser($request));

        return new HouseholdInvitationResource($invitation->fresh()->load(['household', 'inviter']));
    }

    /**
     * Cancel a household invitation.
     */
    public function cancelInvitation(HouseholdInvitation $invitation, CancelInvitation $action, Request $request): HouseholdInvitationResource
    {
        $action->execute($invitation, $this->authenticatedUser($request));

        return new HouseholdInvitationResource($invitation->fresh()->load(['household', 'inviter']));
    }

    /**
     * Switch the active household of the authenticated user.
     */
    public function switchActive(Household $household, SwitchActiveHousehold $action, Request $request): JsonResponse
    {
        $action->execute($household, $this->authenticatedUser($request));

        return response()->json(['message' => 'Hogar activo cambiado.']);
    }

    /**
     * Remove a member from the given household.
     */
    public function removeMember(Household $household, HouseholdMember $member, RemoveMember $action, RemoveHouseholdMemberRequest $request): Response
    {
        $action->execute($household, $member);

        return response()->noContent();
    }

    /**
     * Create a reusable invite link for the household.
     *
     * @param  CreateHouseholdInviteLinkRequest  $request  The incoming request with the link limits.
     * @param  Household  $household  The household the link belongs to.
     * @param  CreateHouseholdInviteLink  $action  The action responsible for the operation.
     * @return JsonResponse The created invite link payload.
     */
    public function storeInviteLink(CreateHouseholdInviteLinkRequest $request, Household $household, CreateHouseholdInviteLink $action): JsonResponse
    {

        $link = $action->execute(CreateInviteLinkData::fromArray($request->validated()), $household, $request->user());

        return new JsonResponse(['data' => new HouseholdInviteLinkResource($link)], 201);
    }

    /**
     * Revoke a reusable invite link of the household.
     *
     * @param  Household  $household  The household the link belongs to.
     * @param  HouseholdInviteLink  $link  The invite link to revoke.
     * @param  RevokeHouseholdInviteLink  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function destroyInviteLink(Household $household, HouseholdInviteLink $link, RevokeHouseholdInviteLink $action, RevokeHouseholdInviteLinkRequest $request): Response
    {

        $action->execute($link);

        return response()->noContent();
    }

    /**
     * Link or unlink a household member with one of the viewer's contacts.
     *
     * @param  Household  $household  The household the member belongs to.
     * @param  HouseholdMember  $member  The member to relink.
     * @return JsonResponse The updated member payload.
     */
    public function linkMemberContact(LinkHouseholdMemberContactRequest $request, Household $household, HouseholdMember $member): JsonResponse
    {

        $user = $this->authenticatedUser($request);

        $validated = $request->validated();

        $member->update(['contact_id' => $validated['contact_id'] ?? null]);

        return new JsonResponse((new HouseholdMemberResource($member->load('user')))->resolve($request));
    }

    /**
     * Serve the household image.
     */
    public function image(Household $household, ViewHouseholdRequest $request): BinaryFileResponse
    {

        if (! $household->image_url) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($household->image_url)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($household->image_url),
            ['Content-Type' => Storage::disk('local')->mimeType($household->image_url)]
        );
    }
}
