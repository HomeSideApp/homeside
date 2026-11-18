<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * The invitation completion flow for a pending, administrator-created account.
 *
 * The signed invitation URL proves control of the invited mailbox and Google
 * proves control of the same mailbox, so the pair is strong enough to complete
 * the pre-created account without weakening the login rule.
 */
class InvitationGoogleCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->freezeTime();
        Mail::fake();
        Notification::fake();
    }

    public function test_start_route_writes_the_session_marker_and_redirects_to_google(): void
    {
        $invitee = $this->pendingInvitee();
        $this->fakeGoogle('google-happy', $invitee->email);

        $response = $this->get($this->startUrl($invitee));

        $response->assertRedirect('https://socialite.fake/google/authorize');
        $response->assertSessionHas('invitation.google.pending', fn (array $marker): bool => $marker['user_id'] === $invitee->id
            && $marker['email'] === $invitee->email
            && $marker['expires_at'] === now()->addMinutes(10)->getTimestamp());
        $this->assertNull($invitee->fresh()->email_verified_at);
        $this->assertDatabaseCount('google_identities', 0);
    }

    public function test_start_route_requires_a_valid_signature(): void
    {
        $invitee = $this->pendingInvitee();

        $this->get(route('invitation.google.redirect', ['user' => $invitee->id]))->assertForbidden();

        $this->assertNull(session('invitation.google.pending'));
    }

    public function test_start_route_rejects_an_already_completed_account(): void
    {
        $completed = User::factory()->create(['email' => 'completed@example.com']);

        $this->get($this->startUrl($completed))->assertNotFound();

        $this->assertNull(session('invitation.google.pending'));
    }

    public function test_pending_invitee_completes_the_invitation_with_google(): void
    {
        config(['auth.google.require_otp' => true]);

        $invitee = $this->pendingInvitee();
        $this->fakeGoogle('google-happy', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $response = $this->get($this->callbackUrl($invitee));

        $response->assertRedirect();
        $this->assertStringContainsString('auth/bootstrap', (string) $response->headers->get('Location'));

        $invitee->refresh();
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertDatabaseHas('google_identities', [
            'user_id' => $invitee->id,
            'google_sub' => 'google-happy',
        ]);
        $this->assertGuest();
        $this->assertNull(session('invitation.google.pending'));
    }

    public function test_delegated_policy_signs_the_invitee_in_without_enrolment(): void
    {
        $invitee = $this->pendingInvitee();
        $invitee->assignRole('user');
        $this->fakeGoogle('google-delegated', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $response = $this->get($this->callbackUrl($invitee));

        $response->assertRedirect('/dashboard');

        $invitee->refresh();
        $this->assertAuthenticatedAs($invitee);
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertDatabaseHas('google_identities', [
            'user_id' => $invitee->id,
            'google_sub' => 'google-delegated',
        ]);
        $this->assertDatabaseCount('authentication_bootstraps', 0);
        Notification::assertNothingSent();
        $this->assertNull(session('invitation.google.pending'));
    }

    public function test_delegated_policy_grants_the_default_role_to_a_roleless_invitee(): void
    {
        $invitee = $this->pendingInvitee(['email' => 'roleless@example.com']);
        $this->assertTrue($invitee->roles()->doesntExist(), 'The invitation deliberately starts without a role.');
        $this->fakeGoogle('google-roleless', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->get($this->callbackUrl($invitee))->assertRedirect('/dashboard');

        // The baseline role is what makes the Google-linked account usable: a
        // Google identity with no role is exactly what the access gate refuses.
        $invitee->refresh();
        $this->assertTrue($invitee->hasRole('user'));
        $this->assertSame(['user'], $invitee->roles()->pluck('slug')->all());
        $this->assertTrue($invitee->canAccessApplication());
        $this->assertAuthenticatedAs($invitee);
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertDatabaseHas('google_identities', [
            'user_id' => $invitee->id,
            'google_sub' => 'google-roleless',
        ]);
        $this->assertDatabaseCount('authentication_bootstraps', 0);
    }

    public function test_delegated_policy_keeps_the_roles_the_administrator_chose(): void
    {
        $invitee = $this->pendingInvitee();
        $invitee->assignRole('admin');
        $this->fakeGoogle('google-admin-invitee', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->get($this->callbackUrl($invitee))->assertRedirect('/dashboard');

        $invitee->refresh();
        $this->assertSame(['admin'], $invitee->roles()->pluck('slug')->all());
        $this->assertFalse($invitee->hasRole('user'), 'An explicit role is never widened with the baseline role.');
        $this->assertAuthenticatedAs($invitee);
    }

    public function test_delegated_policy_refuses_an_invitee_who_cannot_access_the_application(): void
    {
        $invitee = $this->pendingInvitee(['account_status' => AccountStatus::Suspended]);
        $this->fakeGoogle('google-suspended', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertGuest();

        // The completion and the identity link already committed stand: the
        // account is now Google-linked but suspended, so the very same gate
        // a Google login applies withholds the session and nothing else.
        $invitee->refresh();
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertSame(AccountStatus::Suspended, $invitee->account_status);
        $this->assertFalse($invitee->canAccessApplication());
        $this->assertDatabaseHas('google_identities', [
            'user_id' => $invitee->id,
            'google_sub' => 'google-suspended',
        ]);
        $this->assertDatabaseCount('authentication_bootstraps', 0);
    }

    public function test_a_different_google_address_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $passwordHash = $invitee->password;
        $this->fakeGoogle('google-other', 'someone-else@example.com');

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 0);
        $this->assertPendingAndUntouched($invitee, $passwordHash);
    }

    public function test_an_unverified_google_address_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $passwordHash = $invitee->password;
        $this->fakeGoogle('google-unverified', $invitee->email, verified: false);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 0);
        $this->assertPendingAndUntouched($invitee, $passwordHash);
    }

    public function test_a_missing_marker_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $passwordHash = $invitee->password;
        $this->fakeGoogle('google-without-marker', $invitee->email);

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 0);
        $this->assertPendingAndUntouched($invitee, $passwordHash);
    }

    public function test_a_replayed_marker_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $this->fakeGoogle('google-replayed', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();
        $this->get($this->callbackUrl($invitee))->assertRedirect();
        $this->assertNotNull($invitee->fresh()->email_verified_at);

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 1);
        $this->assertSame(1, User::query()->count());
    }

    public function test_an_expired_marker_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $passwordHash = $invitee->password;
        $this->fakeGoogle('google-late', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();
        $this->travel(11)->minutes();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 0);
        $this->assertPendingAndUntouched($invitee, $passwordHash);
    }

    public function test_an_account_completed_between_start_and_callback_is_refused(): void
    {
        $invitee = $this->pendingInvitee();
        $this->fakeGoogle('google-too-late', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $completedAt = now();
        $invitee->forceFill([
            'password' => 'completed-somewhere-else',
            'email_verified_at' => $completedAt,
        ])->save();
        $passwordHash = $invitee->fresh()->password;

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 0);
        $invitee->refresh();
        $this->assertSame($passwordHash, $invitee->password);
        $this->assertSame($completedAt->getTimestamp(), $invitee->email_verified_at?->getTimestamp());
    }

    public function test_a_google_account_already_linked_to_someone_else_is_refused(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $owner->googleIdentity()->create(['google_sub' => 'google-taken']);
        $invitee = $this->pendingInvitee();
        $passwordHash = $invitee->password;
        $this->fakeGoogle('google-taken', $invitee->email);

        $this->get($this->startUrl($invitee))->assertRedirect();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($invitee)));

        $this->assertDatabaseCount('google_identities', 1);
        $this->assertDatabaseHas('google_identities', ['user_id' => $owner->id, 'google_sub' => 'google-taken']);
        $this->assertPendingAndUntouched($invitee, $passwordHash);
    }

    public function test_a_marker_for_another_invitee_is_refused(): void
    {
        $invited = $this->pendingInvitee();
        $other = $this->pendingInvitee(['email' => 'other-invitee@example.com']);
        $otherPasswordHash = $other->password;
        $this->fakeGoogle('google-other-invitee', $other->email);

        $this->get($this->startUrl($invited))->assertRedirect();

        $this->assertRefusedGoogleCompletion($this->get($this->callbackUrl($other)));

        $this->assertDatabaseCount('google_identities', 0);
        $this->assertPendingAndUntouched($other, $otherPasswordHash);
    }

    /**
     * Create a pending, administrator-invited account.
     *
     * @param  array<string, mixed>  $attributes  Overrides for the account.
     * @return User The pending invitee.
     */
    private function pendingInvitee(array $attributes = []): User
    {
        return User::factory()->unverified()->create(array_merge([
            'name' => 'invited@example.com',
            'email' => 'invited@example.com',
        ], $attributes));
    }

    /**
     * Build the signed Google start URL for an invitee.
     *
     * @param  User  $user  The invited account.
     * @return string The signed URL.
     */
    private function startUrl(User $user): string
    {
        return URL::temporarySignedRoute('invitation.google.redirect', now()->addMinutes(30), ['user' => $user->getRouteKey()]);
    }

    /**
     * Build the unsigned Google callback URL for an invitee.
     *
     * @param  User  $user  The invited account.
     * @return string The callback URL.
     */
    private function callbackUrl(User $user): string
    {
        return route('invitation.google.callback', ['user' => $user->getRouteKey()]);
    }

    /**
     * Assert the Google completion was refused without leaking an exception.
     *
     * @param  TestResponse  $response  The callback response.
     */
    private function assertRefusedGoogleCompletion(TestResponse $response): void
    {
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('google');
    }

    /**
     * Assert the account is still pending and no write has happened to it.
     *
     * @param  User  $user  The invited account.
     * @param  string|null  $passwordHash  The password hash recorded before the flow.
     */
    private function assertPendingAndUntouched(User $user, ?string $passwordHash): void
    {
        $user->refresh();

        $this->assertNull($user->email_verified_at);
        $this->assertSame($passwordHash, $user->password);
    }

    /**
     * Fake the Google driver with the identity Google would report.
     *
     * @param  string  $sub  The Google subject identifier.
     * @param  string  $email  The address Google reports.
     * @param  bool  $verified  Whether Google reports the address as verified.
     */
    private function fakeGoogle(string $sub, string $email, bool $verified = true): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'id' => $sub,
            'email' => $email,
            'email_verified' => $verified,
        ]));
    }
}
