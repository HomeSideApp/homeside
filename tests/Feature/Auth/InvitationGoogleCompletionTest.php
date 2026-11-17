<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
