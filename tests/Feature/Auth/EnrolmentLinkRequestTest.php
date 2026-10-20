<?php

namespace Tests\Feature\Auth;

use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use App\Notifications\EnrolmentLinkNotification;
use App\Notifications\ResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the public "I do not have an enrolment link" flow.
 *
 * It is unauthenticated and it sends mail, so it has two duties: only an
 * account that could redeem an enrolment link may produce one, and every
 * request gets the SAME answer whether the account exists, already has a second
 * factor, is blocked or is unknown. A validation error must not become the
 * oracle the response refuses to be.
 */
class EnrolmentLinkRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_is_public_and_creates_no_session(): void
    {
        $response = $this->get(route('auth.enrolment.resend'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/ResendEnrolmentLink')
                ->where('mode', 'request')
                ->where('requestUrl', route('auth.enrolment.resend.store'))
            );

        $this->assertGuest();
        $this->assertNull(session('login.id'));
        $this->assertSame(0, AuthenticationBootstrap::query()->count());
        $this->assertStringNotContainsString('name="password"', (string) $response->getContent());
    }

    public function test_an_account_without_a_second_factor_receives_the_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'needs-enrolment@example.com']);

        $this->post(route('auth.enrolment.resend.store'), ['email' => 'needs-enrolment@example.com'])
            ->assertRedirect(route('auth.enrolment.resend'))
            ->assertSessionHas('status', __('app.auth.enrolment_link_sent'));

        Notification::assertSentTo($user, EnrolmentLinkNotification::class);

        $this->assertSame(
            1,
            AuthenticationBootstrap::query()
                ->where('user_id', $user->id)
                ->where('purpose', AuthenticationBootstrapPurpose::TwoFactorEnrolment->value)
                ->whereNull('consumed_at')
                ->count(),
        );

        $this->assertGuest();
    }

    public function test_the_address_is_matched_after_normalisation(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'normalised@example.com']);

        $this->post(route('auth.enrolment.resend.store'), ['email' => '  Normalised@Example.COM '])
            ->assertRedirect(route('auth.enrolment.resend'));

        Notification::assertSentTo($user, EnrolmentLinkNotification::class);
    }

    public function test_only_an_account_that_could_redeem_the_link_receives_one(): void
    {
        Notification::fake();

        $waiting = User::factory()->create(['email' => 'waiting@example.com']);
        $enrolled = User::factory()->withTwoFactor()->create(['email' => 'enrolled@example.com']);
        $blocked = User::factory()->blocked()->create(['email' => 'blocked-link@example.com']);

        $cases = [
            'waiting for enrolment' => 'waiting@example.com',
            'unknown' => 'nobody@example.com',
            'already enrolled' => 'enrolled@example.com',
            'blocked' => 'blocked-link@example.com',
        ];

        $baseline = null;
        $index = 0;

        foreach ($cases as $label => $email) {
            // Distinct source address per case: the per-IP throttle is not what
            // this test is about, and each case must be judged on its own.
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$index]);
            $index++;

            $response = $this->post(route('auth.enrolment.resend.store'), ['email' => $email])
                ->assertRedirect(route('auth.enrolment.resend'))
                ->assertSessionHas('status', __('app.auth.enrolment_link_sent'));

            $baseline ??= $this->responseShape($response);
            $this->assertSame($baseline, $this->responseShape($response), 'The '.$label.' case must answer identically.');
            $this->assertGuest();
        }

        // Only the account waiting for enrolment produced a grant.
        Notification::assertSentToTimes($waiting, EnrolmentLinkNotification::class, 1);
        Notification::assertNotSentTo($enrolled, EnrolmentLinkNotification::class);
        Notification::assertNotSentTo($blocked, EnrolmentLinkNotification::class);
        Notification::assertCount(1);
        $this->assertSame(1, AuthenticationBootstrap::query()->count());
    }

    public function test_a_precognitive_request_has_no_side_effects(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'precognitive-link@example.com']);

        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('auth.enrolment.resend.store'), ['email' => 'precognitive-link@example.com'])
            ->assertSuccessfulPrecognition();

        $this->assertSame(0, AuthenticationBootstrap::query()->count());
        Notification::assertNothingSent();
        $this->assertGuest();

        // A malformed address is a validation error, not an existence probe.
        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('auth.enrolment.resend.store'), ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, AuthenticationBootstrap::query()->count());
        Notification::assertNothingSent();
        $this->assertSame(1, User::query()->count());
    }

    public function test_the_form_is_rate_limited_per_source_ip(): void
    {
        config([
            'registration.rate_limits.enrolment_link_per_ip' => 1,
            'registration.rate_limits.enrolment_link_per_identifier' => 100,
        ]);

        $this->post(route('auth.enrolment.resend.store'), ['email' => 'first@example.com'])->assertRedirect();

        $this->post(route('auth.enrolment.resend.store'), ['email' => 'second@example.com'])->assertStatus(429);
    }

    public function test_the_form_is_rate_limited_per_normalised_address_across_hosts(): void
    {
        config([
            'registration.rate_limits.enrolment_link_per_ip' => 100,
            'registration.rate_limits.enrolment_link_per_identifier' => 1,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.18.0.1'])
            ->post(route('auth.enrolment.resend.store'), ['email' => 'Target@Example.com'])
            ->assertRedirect();

        $this->withServerVariables(['REMOTE_ADDR' => '198.18.0.2'])
            ->post(route('auth.enrolment.resend.store'), ['email' => 'target@example.com'])
            ->assertStatus(429);
    }

    public function test_this_flow_is_not_interchangeable_with_otp_recovery(): void
    {
        Notification::fake();

        // An account with no second factor gets an ENROLMENT link here...
        $user = User::factory()->create(['email' => 'purposes@example.com']);

        $this->post(route('auth.enrolment.resend.store'), ['email' => 'purposes@example.com'])
            ->assertRedirect(route('auth.enrolment.resend'));

        $url = null;
        Notification::assertSentTo($user, EnrolmentLinkNotification::class, function (EnrolmentLinkNotification $notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertIsString($url);
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));
        $token = $segments[2] ?? '';

        // ...and that enrolment token is worthless on the recovery endpoint.
        $this->get(route('otp.reset', ['token' => $token]))->assertForbidden();
        $this->get(route('auth.bootstrap.two-factor.show', ['token' => $token]))->assertOk();

        // Recovery keeps its guard: an account without a factor is refused, and
        // no recovery token is minted for it.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->post(route('otp.forgot.store'), ['email' => 'purposes@example.com'])
            ->assertSessionHas('status', __('app.auth.otp_reset_link_sent'));

        Notification::assertNotSentTo($user, ResetOtpNotification::class);
        $this->assertSame(
            0,
            AuthenticationBootstrap::query()
                ->where('purpose', AuthenticationBootstrapPurpose::AccountRecovery->value)
                ->count(),
        );
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
