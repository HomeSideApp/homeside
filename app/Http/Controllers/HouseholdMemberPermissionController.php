<?php

namespace App\Http\Controllers;

use App\Actions\Households\UpdateMemberPermissions;
use App\Http\Requests\UpdateHouseholdMemberPermissionsRequest;
use App\Models\Household;
use App\Models\HouseholdMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class HouseholdMemberPermissionController extends Controller
{
    public function __invoke(UpdateHouseholdMemberPermissionsRequest $request, Household $household, HouseholdMember $member, UpdateMemberPermissions $action): JsonResponse|RedirectResponse
    {
        $result = $action->execute($household, $member, $this->authenticatedUser($request), $request->validated());

        return $request->expectsJson()
            ? response()->json(['data' => $result])
            : back();
    }
}
