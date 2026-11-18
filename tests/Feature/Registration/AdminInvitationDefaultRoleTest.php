<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\ExternalIdentityData;
use App\Data\Auth\RegisterAccountData;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The roles an administrator-invited account ends up with once it completes.
 *
 * The roles an administrator chose when the account was created must win
 * untouched. A role-less invitation still has to end with a usable account,
 * because an account carrying a Google identity and no role fails
 * `canAccessApplication()` and can therefore never be signed in. The other
 * channels keep granting the baseline role exactly as before.
 */
class AdminInvitationDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
    }

    public function test_a_roleless_invitation_completed_with_a_password_receives_the_default_role(): void
    {
        $this->seedRoles();
        $invitee = $this->pendingInvitee();
        $this->assertTrue($invitee->roles()->doesntExist(), 'The invitation deliberately starts without a role.');

        $this->completeWithPassword($invitee)->assertRedirect();

        $invitee->refresh();
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertSame(['user'], $invitee->roles()->pluck('slug')->all());
    }

    public function test_an_invitation_with_an_explicit_role_keeps_only_that_role(): void
    {
        $this->seedRoles();
        $invitee = $this->pendingInvitee();
        $invitee->assignRole('admin');

        $this->completeWithPassword($invitee)->assertRedirect();

        $invitee->refresh();
        $this->assertSame(['admin'], $invitee->roles()->pluck('slug')->all());
        $this->assertFalse($invitee->hasRole('user'), 'An explicit role is never widened with the baseline role.');
    }

    public function test_the_default_role_is_skipped_when_its_slug_does_not_exist(): void
    {
        $invitee = $this->pendingInvitee();

        $this->completeWithPassword($invitee)->assertRedirect();

        $invitee->refresh();
        $this->assertNotNull($invitee->email_verified_at);
        $this->assertTrue($invitee->roles()->doesntExist());
    }

    public function test_the_other_channels_keep_granting_the_default_role(): void
    {
        $this->seedRoles();
        $settings = app(RegistrationSettings::class);

        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);
        $web = $this->register(RegistrationChannel::Web, 'web-baseline@example.com', password: 'password123');

        $settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);
        $api = $this->register(RegistrationChannel::Api, 'api-baseline@example.com', password: 'password123');

        $settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Open);
        $google = $this->register(
            RegistrationChannel::Google,
            'google-baseline@example.com',
            externalSubject: 'google-baseline-sub',
        );

        $this->assertSame(['user'], $web->roles()->pluck('slug')->all());
        $this->assertSame(['user'], $api->roles()->pluck('slug')->all());
        $this->assertSame(['user'], $google->roles()->pluck('slug')->all());
    }

    /**
     * Seed the system roles the default-role rule looks up.
     */
    private function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
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
     * Complete a pending invitation through the signed password form.
     *
     * @param  User  $invitee  The invited account.
     * @return TestResponse The invitation response.
     */
    private function completeWithPassword(User $invitee): TestResponse
    {
        return $this->post(URL::temporarySignedRoute('invitation.store', now()->addMinutes(30)), [
            'token' => $invitee->getRouteKey(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
    }

    /**
     * Register a brand new account through the shared use case.
     *
     * @param  RegistrationChannel  $channel  The channel to register with.
     * @param  string  $email  The address to register.
     * @param  string|null  $password  The chosen password, when the channel takes one.
     * @param  string|null  $externalSubject  The Google subject, when the channel needs one.
     * @return User The created account.
     */
    private function register(
        RegistrationChannel $channel,
        string $email,
        ?string $password = null,
        ?string $externalSubject = null,
    ): User {
        $outcome = app(RegisterAccount::class)->execute(new RegisterAccountData(
            name: 'Baseline Person',
            email: $email,
            password: $password,
            locale: 'en-US',
            channel: $channel,
            externalIdentity: $externalSubject !== null
                ? new ExternalIdentityData('google', $externalSubject, $email, true)
                : null,
            origin: 'web',
        ));

        $this->assertTrue($outcome->registered(), 'The channel registration must succeed.');
        $this->assertNotNull($outcome->user);

        return $outcome->user;
    }
}
