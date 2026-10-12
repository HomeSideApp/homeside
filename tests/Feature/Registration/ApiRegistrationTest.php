<?php

namespace Tests\Feature\Registration;

use App\Enums\ApprovalStatus;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Covers the gated public API registration endpoint.
 */
class ApiRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array<string, string> */
    private function payload(string $email): array
    {
        return [
            'name' => 'API User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    public function test_api_registration_is_disabled_by_default(): void
    {
        Notification::fake();

        $response = $this->postJson(route('api.v1.auth.register'), $this->payload('blocked@example.com'));

        $response->assertStatus(403)->assertJson(['code' => 'registration_disabled']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
        Notification::assertNothingSent();
    }

    public function test_api_registration_succeeds_when_the_channel_is_open(): void
    {
        Notification::fake();
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);

        $response = $this->postJson(route('api.v1.auth.register'), $this->payload('open@example.com'));

        $response->assertCreated();

        $user = User::where('email', 'open@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
        $this->assertSame(ApprovalStatus::Approved->value, $user->approval_status);
        Notification::assertSentTo($user, MobileVerifyEmailNotification::class);
    }

    public function test_api_registration_waits_for_approval_when_required(): void
    {
        Notification::fake();
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);

        $response = $this->postJson(route('api.v1.auth.register'), $this->payload('pending@example.com'));

        $response->assertCreated()->assertJson(['state' => 'approval_pending']);
        $this->assertNull($response->json('token'));
        $this->assertNull($response->json('data.token'));

        $user = User::where('email', 'pending@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Pending->value, $user->approval_status);
        $this->assertTrue($user->hasRole('user'));
        Notification::assertSentTo($user, MobileVerifyEmailNotification::class);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_pending_account_cannot_authenticate(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);

        $this->postJson(route('api.v1.auth.register'), $this->payload('pending-login@example.com'))
            ->assertCreated();

        $login = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'pending-login@example.com',
            'password' => 'password123',
        ]);

        $login->assertForbidden();
        $this->assertGuest();

        $user = User::where('email', 'pending-login@example.com')->firstOrFail();
        $this->assertSame(0, $user->tokens()->count());
    }
}
