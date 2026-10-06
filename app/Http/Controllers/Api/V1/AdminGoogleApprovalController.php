<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ResolveGoogleApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveGoogleApprovalApiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminGoogleApprovalController extends Controller
{
    public function approve(ApproveGoogleApprovalApiRequest $request, User $user, ResolveGoogleApproval $approval): JsonResponse
    {
        $validated = $request->validated();
        $approval->approve($user, $validated['roles']);

        return response()->json(['data' => [
            'id' => $user->id,
            'approval_status' => $user->approval_status,
            'roles' => $user->roles()->pluck('slug'),
        ]]);
    }

    public function reject(User $user, ResolveGoogleApproval $approval): Response
    {
        $approval->reject($user);

        return response()->noContent();
    }
}
