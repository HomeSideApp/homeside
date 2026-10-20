<?php

namespace Tests\Feature\Auth;

use App\Contracts\AuthenticationBootstrapRepository;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use App\Notifications\EnrolmentLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the "my enrolment link expired" way back in.
 *
 * The classifying lookup tells a genuine but expired link apart from an unknown
 * or already used one, so ONLY the genuine expired case is offered a resend.
 * Everything else — unknown, consumed, superseded, an account that already
 * enrolled, an account without access — gets one identical generic state with
 * no action, and no request ever creates a session.
 */
class EnrolmentLinkResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expired_link_offers_a_resend_without_creating_a_session(): void
    {
        $user = User::factory()->create(['email' => 'expired-link@example.com']);
        $token = $this->expiredToken($user);

        $response = $this->get($this->showUrl($token))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/ResendEnrolmentLink')
                ->where('mode', 'expired')
                ->where('resendUrl', route('auth.bootstrap.two-factor.resend', ['token' => $token]))
            );

        $this->assertGuest();
        $this->assertNull(session('login.id'));

        // The page never reveals whose link it is.
        $this->assertStringNotContainsString($user->email, (string) $response->getContent());
    }

    public function test_the_resend_issues_a_new_link_and_kills_the_old_one(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = $this->expiredToken($user);

        $this->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))
            ->assertRedirect(route('auth.enrolment.resend'))
            ->assertSessionHas('status', __('app.auth.enrolment_link_sent'));

        $url = null;
        Notification::assertSentTo($user, EnrolmentLinkNotification::class, function (EnrolmentLinkNotification $notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertIsString($url);

        // One live link per user and purpose: the reissued grant replaced the
        // old one, so the link the visitor could not use is gone.
        $this->assertSame(1, AuthenticationBootstrap::query()->count());
        $this->assertNull(
            app(AuthenticationBootstrapRepository::class)->findAnyState($token, AuthenticationBootstrapPurpose::TwoFactorEnrolment),
        );

        $this->get($this->showUrl($token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('app.errors.bootstrap_invalid'));

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('auth/OtpSetup'));

        $this->assertGuest();
    }

    public function test_only_a_genuine_expired_link_is_resendable(): void
    {
        Notification::fake();

        $consumedUser = User::factory()->create();
        $consumedToken = app(AuthenticationBootstrapRepository::class)
            ->issue($consumedUser, AuthenticationBootstrapPurpose::TwoFactorEnrolment, 30);
        $this->assertNotNull(
            app(AuthenticationBootstrapRepository::class)
                ->consume($consumedToken, AuthenticationBootstrapPurpose::TwoFactorEnrolment),
        );

        $enrolledToken = $this->expiredToken(User::factory()->withTwoFactor()->create());
        $blockedToken = $this->expiredToken(User::factory()->blocked()->create());
        $rejectedToken = $this->expiredToken(User::factory()->create(['approval_status' => 'rejected']));
        $suspendedToken = $this->expiredToken(User::factory()->suspended()->create());

        $cases = [
            'unknown' => null,
            'consumed' => $consumedToken,
            'already enrolled' => $enrolledToken,
            'blocked' => $blockedToken,
            'rejected' => $rejectedToken,
            'suspended' => $suspendedToken,
        ];

        $baselineGet = null;
        $baselinePost = null;
        $index = 0;

        foreach ($cases as $label => $token) {
            // A distinct source address per case keeps the shared per-IP GET
            // throttle (which this test is not about) out of the comparison.
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$index]);
            $index++;

            $get = $this->get($token === null ? $this->showUrl('not-a-real-token') : $this->showUrl($token))
                ->assertRedirect(route('login'))
                ->assertSessionHas('status', __('app.errors.bootstrap_invalid'));

            $baselineGet ??= $this->responseShape($get);
            $this->assertSame($baselineGet, $this->responseShape($get), 'The '.$label.' state must not be distinguishable.');

            if ($token !== null) {
                // Forcing the resend anyway changes nothing: no grant, no mail
                // and the same generic answer.
                $post = $this->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))
                    ->assertRedirect(route('auth.enrolment.resend'))
                    ->assertSessionHas('status', __('app.auth.enrolment_link_sent'));

                $baselinePost ??= $this->responseShape($post);
                $this->assertSame($baselinePost, $this->responseShape($post), 'The '.$label.' resend must not be distinguishable.');
            }

            $this->assertGuest();
        }

        Notification::assertNothingSent();

        // Nothing was reissued: no grant is left redeemable, whatever its owner.
        $this->assertSame(
            0,
            AuthenticationBootstrap::query()
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->count(),
        );
    }

    public function test_the_resend_is_rate_limited_per_source_ip(): void
    {
        config([
            'registration.rate_limits.enrolment_resend_per_ip' => 1,
            'registration.rate_limits.enrolment_resend_per_token' => 100,
        ]);

        $user = User::factory()->create();
        $token = $this->expiredToken($user);

        $this->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))->assertRedirect();

        $this->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))->assertStatus(429);
    }

    public function test_the_resend_is_rate_limited_per_token_across_addresses(): void
    {
        config([
            'registration.rate_limits.enrolment_resend_per_ip' => 100,
            'registration.rate_limits.enrolment_resend_per_token' => 1,
        ]);

        $user = User::factory()->create();
        $token = $this->expiredToken($user);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))
            ->assertRedirect();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
            ->post(route('auth.bootstrap.two-factor.resend', ['token' => $token]))
            ->assertStatus(429);
    }

    /**
     * Issue an enrolment grant that is already past its expiry.
     *
     * No sleeping: the grant is issued with a negative lifetime so the row is
     * genuinely expired.
     *
     * @param  User  $user  The grant's owner.
     * @return string The plaintext token.
     */
    private function expiredToken(User $user): string
    {
        return app(AuthenticationBootstrapRepository::class)
            ->issue($user, AuthenticationBootstrapPurpose::TwoFactorEnrolment, -1);
    }

    private function showUrl(string $token): string
    {
        return route('auth.bootstrap.two-factor.show', ['token' => $token]);
    }

    /**
     * Reduce a response to the parts a client could tell apart.
     *
     * @param  TestResponse<RedirectResponse>  $response  The response under test.
     * @return array{status: int, location: string|null, body: string} The comparable shape.
     */
    private function responseShape(TestResponse $response): array
    {
        return [
            'status' => $response->getStatusCode(),
            'location' => $response->headers->get('Location'),
            'body' => (string) $response->getContent(),
        ];
    }
}
