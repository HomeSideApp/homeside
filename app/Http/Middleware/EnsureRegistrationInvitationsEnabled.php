<?php

namespace App\Http\Middleware;

use App\Enums\RegistrationChannel;
use App\Services\Auth\RegistrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an invitation flow whose channel is disabled by the installation.
 *
 * This is the single enforcement point for the two InvitationPolicy settings:
 * every route that starts a registration through an invitation is wrapped with
 * it, so the check never has to be repeated inside a controller or a form
 * request. A disabled channel answers 404 with the flow's own generic message,
 * which keeps the refusal indistinguishable from an unusable or expired
 * invitation and therefore does not enumerate tokens.
 *
 * The middleware reads the policy on every request, so re-enabling the channel
 * from the admin panel restores the route immediately.
 */
final class EnsureRegistrationInvitationsEnabled
{
    public function __construct(private RegistrationSettings $settings) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure(Request): (Response)  $next  The next middleware in the request pipeline.
     * @param  string  $channel  The invitation channel configured on the route.
     * @return Response The HTTP response.
     */
    public function handle(Request $request, Closure $next, string $channel): Response
    {
        $resolved = RegistrationChannel::tryFrom($channel);

        if ($resolved === null || ! $resolved->isInvitation()) {
            abort(500, 'Invitation channel is not configured correctly.');
        }

        if (! $this->settings->invitationsEnabled($resolved)) {
            abort(404, $resolved === RegistrationChannel::HouseholdInvitation
                ? __('app.errors.invite_link_unusable')
                : __('app.errors.invitation_invalid'));
        }

        return $next($request);
    }
}
