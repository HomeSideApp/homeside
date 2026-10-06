<?php

namespace App\Http\Controllers;

use App\Actions\Households\TransferHouseholdOwnership;
use App\Http\Requests\TransferHouseholdOwnershipRequest;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class HouseholdOwnershipController extends Controller
{
    public function __invoke(TransferHouseholdOwnershipRequest $request, Household $household, TransferHouseholdOwnership $action): JsonResponse|RedirectResponse
    {
        $result = $action->execute($household, $this->authenticatedUser($request), $request->string('member_id')->toString());

        return $request->expectsJson()
            ? response()->json(['data' => $result])
            : back();
    }
}
