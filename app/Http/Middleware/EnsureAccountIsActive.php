<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a user whose account is no longer allowed to operate.
 *
 * This is a per-REQUEST check, deliberately not a login-time one: a session
 * that was valid when it was created must lose authorization the moment an
 * administrator suspends, blocks or rejects the account. It is attached to the
 * protected route groups only, so the login, two-factor challenge, email
 * verification and registration flows stay reachable for exactly the states
 * this middleware refuses.
 *
 * Two independent axes are checked:
 *  - `account_status` must be `active` (suspension and blocking clear it);
 *  - `approval_status` must not be `rejected`.
 *
 * `pending` is intentionally NOT refused here: a pending account is refused by
 * the approval middleware, while `account_status` and a persisted rejection are
 * the two conditions that can appear on an otherwise-approved session.
 */
final class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming request.
     * @param  Closure(Request): Response  $next  The next middleware in the pipeline.
     * @return Response The response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->account_status instanceof AccountStatus && ! $user->account_status->allowsAccess()) {
            abort(403, __('app.errors.account_not_active'));
        }

        if ($user->approval_status === ApprovalStatus::Rejected->value) {
            abort(403, __('app.errors.account_not_active'));
        }

        return $next($request);
    }
}
