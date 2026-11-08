<?php

namespace Tests\Feature\Registration;

use App\Contracts\RegistrationGate;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Models\HouseholdInviteLink;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Regression coverage: the invitation registration and the normal password +
 * TOTP login must keep working while the two live holes are closed.
 */
class RegistrationRegressionTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('user');
        Role::where('slug', 'user')->firstOrFail()->syncPermissions(Permission::all());

        $this->createHouseholdForUser($this->admin);
    }

    public function test_household_invitation_registration_still_succeeds_end_to_end(): void
    {
        $link = HouseholdInviteLink::create([
            'household_id' => $this->household->id,
            'created_by' => $this->admin->id,
            'token' => 'regression-token',
        ]);

        $response = $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Invited Person',
            'email' => 'invited@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('auth/bootstrap', (string) $response->headers->get('Location'));

        $user = User::where('email', 'invited@example.com')->firstOrFail();
        // A generic, reusable invite link never proves ownership of the typed
        // address, so it must NOT auto-verify the email.
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('user'));
        $this->assertDatabaseHas('household_members', [
            'household_id' => $this->household->id,
            'user_id' => $user->id,
        ]);

        // Onboarding continues on the signed enrolment page, still as a guest.
        $this->get((string) $response->headers->get('Location'))->assertOk();
        $this->assertGuest();
    }

    public function test_the_invitation_channel_is_never_policy_denied(): void
    {
        $decision = app(RegistrationGate::class)->evaluate(RegistrationChannel::HouseholdInvitation, 'invited@example.com');

        $this->assertSame(RegistrationDecision::Allowed, $decision->decision);
    }

    public function test_normal_password_and_totp_login_still_works(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('two-factor.login'));

        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));

        $this->withSession(['login.id' => $user->id])
            ->post(route('two-factor.login.store'), ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }
}
