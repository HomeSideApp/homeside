<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\GetHouseholdDashboard;
use App\Http\Controllers\Controller;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HouseholdDashboardController extends Controller
{
    public function show(Request $request, Household $household, GetHouseholdDashboard $action): JsonResponse
    {
        $this->authorize('view', $household);
        $dashboard = $action->execute($household, $request->user());

        return response()->json(['data' => [
            'household' => ['id' => $household->id, 'name' => $household->name],
            'enabled_modules' => $dashboard['enabled_modules'],
            'shopping_lists' => [
                'active_lists_count' => $dashboard['active_lists_count'],
                'pending_items_count' => $dashboard['pending_items_count'],
            ],
        ]]);
    }
}
