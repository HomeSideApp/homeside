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
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\CreateHouseholdInviteLinkRequest;
use App\Http\Requests\InviteMemberRequest;
use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdSettingsRequest;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
    public function show(Household $household, GetHousehold $action): HouseholdResource
    {
        $this->authorize('view', $household);

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
    public function destroy(Household $household, DeleteHousehold $action): Response
    {
        $this->authorize('delete', $household);
        $action->execute($household);

        return response()->noContent();
    }

    /**
     * Invite a member to the given household.
     */
    public function invite(InviteMemberRequest $request, Household $household, InviteMember $action): JsonResponse
    {
        $this->authorize('invite', $household);
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
    public function acceptInvitation(AcceptInvitationRequest $request, AcceptInvitation $action): HouseholdInvitationResource
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
    public function removeMember(Household $household, HouseholdMember $member, RemoveMember $action): Response
    {
        abort_unless($member->household_id === $household->id, 404);
        $this->authorize('removeMember', [$household, $member]);
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
        $this->authorize('update', $household);

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
    public function destroyInviteLink(Household $household, HouseholdInviteLink $link, RevokeHouseholdInviteLink $action): Response
    {
        $this->authorize('update', $household);
        abort_unless($link->household_id === $household->id, 404);

        $action->execute($link);

        return response()->noContent();
    }

    /**
     * Link or unlink a household member with one of the viewer's contacts.
     *
     * @param  Request  $request  The incoming request carrying the contact id.
     * @param  Household  $household  The household the member belongs to.
     * @param  HouseholdMember  $member  The member to relink.
     * @return JsonResponse The updated member payload.
     */
    public function linkMemberContact(Request $request, Household $household, HouseholdMember $member): JsonResponse
    {
        $this->authorize('view', $household);

        $user = $this->authenticatedUser($request);
        abort_unless($member->household_id === $household->id, 404);
        abort_unless($household->isAdmin($user) || $member->user_id === $user->id, 403);

        $validated = $request->validate([
            'contact_id' => ['nullable', 'uuid', Rule::exists('contacts', 'id')->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereIn('household_id', DB::table('household_members')->where('user_id', $user->id)->select('household_id')))],
        ]);

        $member->update(['contact_id' => $validated['contact_id'] ?? null]);

        return new JsonResponse((new HouseholdMemberResource($member->load('user')))->resolve($request));
    }

    /**
     * Serve the household image.
     */
    public function image(Household $household): BinaryFileResponse
    {
        $this->authorize('view', $household);

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
