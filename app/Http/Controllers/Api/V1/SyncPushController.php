<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sync\PushSyncOperations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SyncPushRequest;
use Illuminate\Http\JsonResponse;

/**
 * Drains the mobile outbox: one idempotent request carries the queued writes and
 * answers one result per operation, in the order the device sent them.
 */
final class SyncPushController extends Controller
{
    /**
     * Re-dispatch every queued operation and report what each one answered.
     */
    public function store(SyncPushRequest $request, PushSyncOperations $action): JsonResponse
    {
        return response()->json([
            'data' => [
                'results' => $action->execute($request, $request->operations()),
            ],
        ]);
    }
}
