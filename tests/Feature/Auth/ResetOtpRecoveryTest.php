<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\CreateAuthenticationBootstrap;
use App\Enums\AuthenticationBootstrapPurpose;
use App\Models\AuthenticationBootstrap;
use App\Models\User;
use App\Notifications\ResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Covers the web OTP recovery flow on top of the single-use `AccountRecovery`
 * bootstrap: the emailed link carries the raw token, only its digest is stored,
 * the grant is redeemed exactly once and recovery never creates a session.
 */
class ResetOtpRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_otp_emails_a_single_use_recovery_link_that_replaces_the_factor(): void
    {
        Notification::fake();

        $user = User::factory()->withTwoFactor()->create();
        $originalSecret = decrypt($user->two_factor_secret);

        $this->post(route('otp.forgot.store'), ['email' => $user->email])
            ->assertSessionHas('status', __('app.auth.otp_reset_link_sent'));

        $url = null;
        Notification::assertSentTo($user, ResetOtpNotification::class, function (ResetOtpNotification $notification) use ($user, &$url): bool {
            $url = $notification->toMail($user)->actionUrl;

            return true;
        });

        $this->assertIsString($url);
        $this->assertStringContainsString('/reset-otp/', $url);
        $token = $this->tokenFrom($url);

        // The raw token never touches the database.
        $this->assertStringNotContainsString(
            $token,
            (string) json_encode(AuthenticationBootstrap::query()->get()->toArray(), JSON_THROW_ON_ERROR),
        );

        // Opening the link creates no session.
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('auth/ResetOtp'));
        $this->assertGuest();

        $bootstrap = AuthenticationBootstrap::query()->where('purpose', 'account_recovery')->firstOrFail();
        $secret = (new Google2FA)->generateSecretKey();

        $this->withSession(['otp_reset_secret_'.$bootstrap->id => $secret])
            ->post(route('otp.reset.confirm', ['token' => $token]), [
                'code' => (new Google2FA)->getCurrentOtp($secret),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/OtpRecoveryCodes')
                ->has('recoveryCodes', 8)
            );

        $user->refresh();
        $this->assertNotSame($originalSecret, decrypt($user->two_factor_secret));
        $this->assertSame($secret, decrypt($user->two_factor_secret));

        // Recovery replaced the factor; it did not sign anybody in.
        $this->assertGuest();
    }

    public function test_a_recovery_token_can_only_be_redeemed_once(): void
    {
        $user = User::factory()->withTwoFactor()->create();
        $token = app(CreateAuthenticationBootstrap::class)
            ->execute($user, AuthenticationBootstrapPurpose::AccountRecovery);
        $bootstrap = AuthenticationBootstrap::query()->where('purpose', 'account_recovery')->firstOrFail();
        $secret = (new Google2FA)->generateSecretKey();
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->withSession(['otp_reset_secret_'.$bootstrap->id => $secret])
            ->post(route('otp.reset.confirm', ['token' => $token]), ['code' => $code])
            ->assertOk();

        $this->post(route('otp.reset.confirm', ['token' => $token]), ['code' => $code])
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_forgot_otp_is_not_an_account_oracle(): void
    {
        Notification::fake();

        $this->post(route('otp.forgot.store'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', __('app.auth.otp_reset_link_sent'));

        Notification::assertNothingSent();
        $this->assertSame(0, AuthenticationBootstrap::query()->count());
    }

    /**
     * Extract the plaintext token from a recovery URL.
     *
     * @param  string  $url  The `/reset-otp/{token}` URL.
     * @return string The plaintext token.
     */
    private function tokenFrom(string $url): string
    {
        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        return $segments[1] ?? '';
    }
}
