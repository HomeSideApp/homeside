<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\UpdateMemberPermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHouseholdMemberPermissionsRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Http\JsonResponse;

/**
 * API surface for updating a household member's permissions.
 *
 * The web controller answers a redirect for browser callers, but an API route
 * must never declare `RedirectResponse`: since the framework version where
 * `RedirectResponse` implements `Responsable`, documenting that return type
 * recurses in Scramble. Both surfaces share the same action and form request.
 */
final class HouseholdMemberPermissionController extends Controller
{
    public function __invoke(
        UpdateHouseholdMemberPermissionsRequest $request,
        Household $household,
        HouseholdMember $member,
        UpdateMemberPermissions $action,
    ): JsonResponse {
        $result = $action->execute($household, $member, $this->authenticatedUser($request), $request->validated());

        return response()->json(['data' => $result]);
    }
}
