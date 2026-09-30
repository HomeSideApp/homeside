<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Economy\GetHouseholdEconomyOverview;
use App\Actions\Economy\GetPersonalEconomyOverview;
use App\Http\Controllers\Controller;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EconomyOverviewController extends Controller
{
    public function household(Request $request, Household $household, GetHouseholdEconomyOverview $action): JsonResponse
    {
        $this->authorize('view', $household);
        $validated = $request->validate([
            'months' => ['sometimes', 'integer', 'between:1,24'],
            'currency' => ['sometimes', 'string', 'size:3', 'uppercase'],
        ]);

        return response()->json(['data' => $action->execute(
            $household,
            $this->authenticatedUser($request),
            $validated['months'] ?? 12,
            $validated['currency'] ?? null,
        )]);
    }

    public function personal(Request $request, GetPersonalEconomyOverview $action): JsonResponse
    {
        $validated = $request->validate([
            'months' => ['sometimes', 'integer', 'between:1,24'],
            'currency' => ['sometimes', 'string', 'size:3', 'uppercase'],
        ]);

        return response()->json(['data' => $action->execute(
            $this->authenticatedUser($request),
            $validated['months'] ?? 12,
            $validated['currency'] ?? null,
        )]);
    }
}
