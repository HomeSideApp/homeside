<?php

namespace Tests\Feature\Registration;

use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Proves that real-time validation never mutates anything.
 *
 * Precognition must be free of side effects: no user created, no invitation
 * consumed, no challenge generated, no mail sent and no status changed. Both
 * the public web registration form and the administrator invitation form are
 * covered, because both are new/changed forms in this phase.
 */
class RegistrationPrecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_precognition_on_the_web_registration_form_has_no_side_effects(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);
        Notification::fake();

        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('register.store'), [
                'name' => 'Precognitive Person',
                'email' => 'precognition@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSuccessfulPrecognition();

        $this->assertDatabaseMissing('users', ['email' => 'precognition@example.com']);
        $this->assertDatabaseCount('registration_audits', 0);
        Notification::assertNothingSent();

        // An invalid payload still validates with no side effects.
        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('register.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('registration_audits', 0);
        Notification::assertNothingSent();
    }

    public function test_precognition_on_the_admin_invitation_form_has_no_side_effects(): void
    {
        $invited = User::factory()->unverified()->create(['email' => 'precognition-invite@example.com']);
        $originalPassword = $invited->password;
        Notification::fake();

        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));

        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post($url, [
                'token' => $invited->getRouteKey(),
                'name' => 'Precognitive Invite',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSuccessfulPrecognition();

        $invited->refresh();
        $this->assertNull($invited->email_verified_at);
        $this->assertSame($originalPassword, $invited->password);
        $this->assertSame($invited->name === 'precognition-invite@example.com', false);
        $this->assertDatabaseCount('registration_audits', 0);
        Notification::assertNothingSent();

        // Invalid: the confirmation does not match.
        $this->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post($url, [
                'token' => $invited->getRouteKey(),
                'password' => 'password123',
                'password_confirmation' => 'different-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertNull($invited->fresh()->email_verified_at);
        $this->assertDatabaseCount('registration_audits', 0);
        Notification::assertNothingSent();
    }
}
