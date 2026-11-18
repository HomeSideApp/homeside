<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sync\GetSyncChanges;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncChangesRequest;
use Illuminate\Http\JsonResponse;

/**
 * Serves the offline delta feed consumed by the mobile outbox.
 */
final class SyncController extends Controller
{
    /**
     * Return every visible row changed after the given opaque cursor.
     */
    public function changes(SyncChangesRequest $request, GetSyncChanges $action): JsonResponse
    {
        return response()->json([
            'data' => $action->execute(
                $request,
                $this->authenticatedUser($request),
                $request->since(),
                $request->limit(),
            ),
        ]);
    }
}
