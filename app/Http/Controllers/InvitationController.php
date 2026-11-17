<?php

namespace App\Http\Controllers;

use App\Actions\Auth\RegisterAccount;
use App\Actions\Auth\ResolveEnrolmentUrl;
use App\Data\Auth\InvitationContextData;
use App\Data\Auth\RegisterAccountData;
use App\Enums\AppLocale;
use App\Enums\RegistrationChannel;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\ViewInvitationRequest;
use App\Models\User;
use App\Services\Auth\GoogleIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * Handles user invitations and password set-up for invited users.
 */
class InvitationController
{
    /**
     * The single-use session marker that authorises the Google callback.
     *
     * @var string
     */
    private const GOOGLE_MARKER_KEY = 'invitation.google.pending';

    /**
     * How long the Google invitation marker stays usable, in minutes.
     *
     * @var int
     */
    private const GOOGLE_MARKER_TTL_MINUTES = 10;

    /**
     * Show the set-password page for a pending invitation.
     *
     * @param  User  $user  The authenticated user.
     * @return Response The HTTP response.
     */
    public function show(ViewInvitationRequest $request, User $user): Response
    {
        return Inertia::render('auth/SetPassword', [
            'token' => $user->getRouteKey(),
            'email' => $user->email,
            'name' => $user->name,
            'storeUrl' => URL::temporarySignedRoute('invitation.store', now()->addMinutes(30)),
        ]);
    }

    /**
     * Complete an invited user's account.
     *
     * The account was already created by an administrator, so this never
     * re-creates it: it routes through the shared RegisterAccount use case with
     * a targeted invitation context, which completes the pre-created row,
     * proves possession of the address (the signed link was mailed to it) and
     * leaves the roles the administrator chose explicitly untouched.
     *
     * @param  AcceptInvitationRequest  $request  The incoming HTTP request.
     * @param  RegisterAccount  $accounts  The shared registration use case.
     * @param  ResolveEnrolmentUrl  $enrolment  The shared next-step decision.
     * @return RedirectResponse The HTTP response.
     */
    public function store(AcceptInvitationRequest $request, RegisterAccount $accounts, ResolveEnrolmentUrl $enrolment): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->invitedUser();

        $outcome = $accounts->execute(new RegisterAccountData(
            name: $validated['name'] ?? null,
            email: $user->email,
            password: $validated['password'],
            locale: $user->locale ?? AppLocale::default()->value,
            channel: RegistrationChannel::AdminInvitation,
            invitation: new InvitationContextData(
                reference: $user->getRouteKey(),
                targetUserId: $user->getRouteKey(),
                emailVerified: true,
                targeted: true,
            ),
            origin: 'web',
        ));

        if (! $outcome->registered() || $outcome->user === null) {
            throw ValidationException::withMessages([
                'password' => __('app.errors.registration_identity_exists'),
            ]);
        }

        return redirect()->to($enrolment->execute($outcome->user));
    }

    /**
     * Start the Google path for a pending invitation.
     *
     * The signed invitation URL is the first half of the authority: it was
     * mailed to the invited address, so possessing it proves control of that
     * mailbox. This route records that fact in a single-use session marker and
     * sends the invitee to Google; the callback supplies the second half.
     *
     * @param  ViewInvitationRequest  $request  The signed invitation request.
     * @param  User  $user  The invited, still pending account.
     * @return SymfonyRedirectResponse The redirect to Google.
     */
    public function googleRedirect(ViewInvitationRequest $request, User $user): SymfonyRedirectResponse
    {
        $request->session()->put(self::GOOGLE_MARKER_KEY, [
            'user_id' => $user->getRouteKey(),
            'email' => Str::lower(trim((string) $user->email)),
            'expires_at' => now()->addMinutes(self::GOOGLE_MARKER_TTL_MINUTES)->getTimestamp(),
        ]);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Complete a pending invitation with a Google identity.
     *
     * Google's redirect carries no signature, so the session marker written by
     * the signed start route is the authority here. It is pulled BEFORE any
     * decision, so a replay finds nothing to trust. The account itself is then
     * re-validated server-side: the marker says which account was invited, it
     * never proves that the account is still pending.
     *
     * The completion and the identity link run inside one transaction, so any
     * refusal writes nothing at all.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  User  $user  The invited account, bound from the route.
     * @param  RegisterAccount  $accounts  The shared registration use case.
     * @param  GoogleIdentityService  $identities  The shared identity linker.
     * @param  ResolveEnrolmentUrl  $enrolment  The shared next-step decision.
     * @return RedirectResponse The HTTP response.
     */
    public function googleCallback(
        Request $request,
        User $user,
        RegisterAccount $accounts,
        GoogleIdentityService $identities,
        ResolveEnrolmentUrl $enrolment,
    ): RedirectResponse {
        $marker = $request->session()->pull(self::GOOGLE_MARKER_KEY);

        if (! $this->googleMarkerAuthorises($marker, $user) || $user->email_verified_at !== null) {
            return $this->refuseGoogleCompletion();
        }

        try {
            $google = Socialite::driver('google')->user();
            $subject = (string) $google->getId();
            $googleEmail = (string) $google->getEmail();
            $googleVerified = ($google->user['email_verified'] ?? false) === true;

            $outcome = DB::transaction(function () use ($accounts, $identities, $user, $subject, $googleEmail, $googleVerified) {
                $outcome = $accounts->execute(new RegisterAccountData(
                    // Keep the display name the administrator chose: this path
                    // only proves the address, it does not collect a new name.
                    name: $user->name,
                    email: (string) $user->email,
                    password: null,
                    locale: $user->locale ?? AppLocale::default()->value,
                    channel: RegistrationChannel::AdminInvitation,
                    invitation: new InvitationContextData(
                        reference: $user->getRouteKey(),
                        targetUserId: $user->getRouteKey(),
                        emailVerified: true,
                        targeted: true,
                    ),
                    origin: 'web',
                ), notify: false);

                // GoogleIdentityService::link enforces the "same verified
                // address" and "not linked elsewhere" rules; a mismatch throws
                // and therefore rolls the whole completion back.
                if ($outcome->registered() && $outcome->user !== null) {
                    $identities->link($outcome->user, $subject, $googleEmail, $googleVerified);
                }

                return $outcome;
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->refuseGoogleCompletion();
        }

        if (! $outcome->registered() || $outcome->user === null) {
            return $this->refuseGoogleCompletion();
        }

        $accounts->dispatchNotifications($outcome, RegistrationChannel::AdminInvitation);

        return redirect()->to($enrolment->execute($outcome->user));
    }

    /**
     * Determine whether the session marker authorises this callback.
     *
     * The marker is only ever written by the signed start route, after
     * ViewInvitationRequest proved the account is pending. It must still name
     * the invited account and still be within its ten minute lifetime.
     *
     * @param  mixed  $marker  The pulled marker, or null when there is none.
     * @param  User  $user  The route-bound account.
     * @return bool True when the marker authorises completing this account.
     */
    private function googleMarkerAuthorises(mixed $marker, User $user): bool
    {
        if (! is_array($marker)) {
            return false;
        }

        $userId = $marker['user_id'] ?? null;
        $email = $marker['email'] ?? null;
        $expiresAt = $marker['expires_at'] ?? null;

        if ((! is_string($userId) && ! is_int($userId)) || ! is_string($email) || ! is_numeric($expiresAt)) {
            return false;
        }

        return (string) $userId === (string) $user->getKey()
            && hash_equals(Str::lower(trim((string) $user->email)), $email)
            && now()->getTimestamp() <= (int) $expiresAt;
    }

    /**
     * Refuse a Google completion without leaking the reason.
     *
     * @return RedirectResponse The redirect back to login.
     */
    private function refuseGoogleCompletion(): RedirectResponse
    {
        return to_route('login')->withErrors([
            'google' => __('app.errors.invitation_google_refused'),
        ]);
    }
}
