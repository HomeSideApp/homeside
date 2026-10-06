<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Auth\ResolveGoogleApproval;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveGoogleApprovalRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class GoogleApprovalController extends Controller
{
    public function approve(ApproveGoogleApprovalRequest $request, User $user, ResolveGoogleApproval $approval): RedirectResponse
    {
        $validated = $request->validated();
        $approval->approve($user, [$validated['role']]);

        return to_route('admin.users', ['approval' => 'pending']);
    }

    public function reject(User $user, ResolveGoogleApproval $approval): RedirectResponse
    {
        $approval->reject($user);

        return to_route('admin.users', ['approval' => 'pending']);
    }
}
