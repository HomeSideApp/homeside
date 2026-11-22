<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\TransferHouseholdOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferHouseholdOwnershipRequest;
use App\Models\Household;
use Illuminate\Http\JsonResponse;

/**
 * API surface for transferring household ownership.
 *
 * The web controller answers a redirect for browser callers, but an API route
 * must never declare `RedirectResponse`: since the framework version where
 * `RedirectResponse` implements `Responsable`, documenting that return type
 * recurses in Scramble. Both surfaces share the same action and form request,
 * so the split adds no business logic.
 */
final class HouseholdOwnershipController extends Controller
{
    public function __invoke(
        TransferHouseholdOwnershipRequest $request,
        Household $household,
        TransferHouseholdOwnership $action,
    ): JsonResponse {
        $result = $action->execute($household, $this->authenticatedUser($request), $request->string('member_id')->toString());

        return response()->json(['data' => $result]);
    }
}
