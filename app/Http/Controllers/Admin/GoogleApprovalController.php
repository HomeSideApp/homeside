<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Auth\ResolveGoogleApproval;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoogleApprovalController extends Controller
{
    public function approve(Request $request, User $user, ResolveGoogleApproval $approval): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', 'string', Rule::exists('roles', 'slug')]]);
        $approval->approve($user, [$validated['role']]);

        return to_route('admin.users', ['approval' => 'pending']);
    }

    public function reject(User $user, ResolveGoogleApproval $approval): RedirectResponse
    {
        $approval->reject($user);

        return to_route('admin.users', ['approval' => 'pending']);
    }
}
