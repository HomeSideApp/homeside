<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ResolveGoogleApproval;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminGoogleApprovalController extends Controller
{
    public function approve(Request $request, User $user, ResolveGoogleApproval $approval): JsonResponse
    {
        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', 'exists:roles,slug'],
        ]);
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
