<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\GetHouseholdEconomyOverview;
use App\Actions\Economy\GetPersonalEconomyOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\EconomyOverviewRequest;
use App\Models\Household;
use Illuminate\Http\JsonResponse;

final class EconomyOverviewController extends Controller
{
    public function household(EconomyOverviewRequest $request, Household $household, GetHouseholdEconomyOverview $action): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(['data' => $action->execute(
            $household,
            $this->authenticatedUser($request),
            $validated['months'] ?? 12,
            $validated['currency'] ?? null,
        )]);
    }

    public function personal(EconomyOverviewRequest $request, GetPersonalEconomyOverview $action): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(['data' => $action->execute(
            $this->authenticatedUser($request),
            $validated['months'] ?? 12,
            $validated['currency'] ?? null,
        )]);
    }
}
