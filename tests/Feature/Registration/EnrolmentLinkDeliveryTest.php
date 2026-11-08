<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use App\Notifications\EnrolmentLinkNotification;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Proves the enrolment link actually reaches the person who needs it.
 *
 * The old flow handed the link to the browser response and nothing else, so a
 * visitor who closed the tab had no way back. Registration and invitation now
 * mail the very same single-use grant, and the mailed link completes the
 * enrolment (TOTP included) exactly as the redirect does.
 */
class EnrolmentLinkDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_mails_a_link_that_completes_the_enrolment(): void
    {
        Notification::fake();
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        $response = $this->post(route('register.store'), [
            'name' => 'Mailed Enrolment',
            'email' => 'mailed-enrolment@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('auth/bootstrap', (string) $response->headers->get('Location'));

        $user = User::where('email', 'mailed-enrolment@example.com')->firstOrFail();

        $url = $this->mailedUrl($user);

        // The link from the mail is a working enrolment link: it opens the QR
        // step without creating a session, and it completes with a valid code.
        $secret = $this->openEnrolmentPage($url);
        $user->markEmailAsVerified();

        $token = $this->tokenFrom($url);
        $bootstrap = $this->activeGrant($token);

        $this->withSession(['auth_bootstrap_secret_'.$bootstrap->id => $secret])
            ->post(route('auth.bootstrap.two-factor.confirm', ['token' => $token]), [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_an_invitation_mails_the_enrolment_link(): void
    {
        Notification::fake();

        $invited = User::factory()->unverified()->create(['email' => 'invited-mail@example.com']);

        $this->post(URL::temporarySignedRoute('invitation.store', now()->addMinutes(30)), [
            'token' => $invited->getRouteKey(),
            'name' => 'Invited Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->openEnrolmentPage($this->mailedUrl($invited));
    }

    public function test_the_mail_states_the_lifetime_and_that_it_can_be_ignored(): void
    {
        Notification::fake();

        $user = User::factory()->create(['name' => 'Lifetime Person']);

        app(CreateAuthenticationBootstrap::class)->execute($user);

        Notification::assertSentTo($user, EnrolmentLinkNotification::class, function (EnrolmentLinkNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            // 12 hours by default: the message says so.
            $this->assertStringContainsString(
                '12 hours',
                (string) json_encode($mail->introLines, JSON_THROW_ON_ERROR),
            );

            // And it tells a person who did not just register what to do.
            $this->assertStringContainsString(
                'ignore this email',
                (string) json_encode($mail->outroLines, JSON_THROW_ON_ERROR),
            );

            return true;
        });
    }

    /**
     * Read the enrolment URL out of the notification sent to the user.
     *
     * @param  User  $user  The recipient.
     * @return string The emailed enrolment URL.
     */
    private function mailedUrl(User $user): string
    {
        $url = null;

        Notification::assertSentTo($user, EnrolmentLinkNotification::class, function (EnrolmentLinkNotification $notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertIsString($url);
        $this->assertStringContainsString('/auth/bootstrap/', $url);

        return $url;
    }

    /**
     * Open the mailed enrolment URL and return the pending TOTP secret.
     *
     * @param  string  $url  The enrolment URL from the notification.
     * @return string The secret shown on the page, stored in the session by the server.
     */
    private function openEnrolmentPage(string $url): string
    {
        $page = $this->get($url)->assertOk();

        $this->assertGuest();

        /** @var array<string, mixed> $payload */
        $payload = $page->viewData('page');

        $this->assertSame('auth/OtpSetup', $payload['component']);

        /** @var array{secret: string} $props */
        $props = $payload['props'];

        return $props['secret'];
    }

    /**
     * Resolve the active enrolment grant behind a mailed URL.
     *
     * @param  string  $token  The plaintext token.
     * @return AuthenticationBootstrap The active grant.
     */
    private function activeGrant(string $token): AuthenticationBootstrap
    {
        $bootstrap = app(AuthenticationBootstrapRepository::class)
            ->findActive($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment);

        $this->assertNotNull($bootstrap);

        return $bootstrap;
    }

    /**
     * Extract the plaintext token from an enrolment URL.
     *
     * @param  string  $url  The `/auth/bootstrap/{token}/2fa` URL.
     * @return string The plaintext token.
     */
    private function tokenFrom(string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        return $segments[2] ?? '';
    }
}
