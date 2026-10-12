<?php

namespace Tests\Feature\Registration;

use App\Enums\HouseholdCreationPolicy;
use App\Enums\InvitationPolicy;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the admin registration-settings surface end to end: authorization,
 * persistence of every setting, validation, Precognition with no side effects
 * and the runtime effect of the two invitation policies and the
 * household-creation policy.
 */
class RegistrationSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_an_authorised_admin_sees_the_settings_page_with_the_persisted_values(): void
    {
        $settings = app(RegistrationSettings::class);
        $settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Open);
        $settings->setInvitationPolicy(RegistrationChannel::HouseholdInvitation, InvitationPolicy::Disabled);
        $settings->setHouseholdCreationPolicy(HouseholdCreationPolicy::Denied);

        $this->actingAs($this->admin())
            ->get(route('admin.registrations.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/registrations/Settings')
                ->where('settings.web', RegistrationPolicy::Disabled->value)
                ->where('settings.api', RegistrationPolicy::Disabled->value)
                ->where('settings.google', RegistrationPolicy::Open->value)
                ->where('settings.household_invitation', InvitationPolicy::Disabled->value)
                ->where('settings.admin_invitation', InvitationPolicy::Enabled->value)
                ->where('settings.household_creation', HouseholdCreationPolicy::Denied->value)
                ->where('options.web', [
                    RegistrationPolicy::Disabled->value,
                    RegistrationPolicy::ApprovalRequired->value,
                    RegistrationPolicy::Open->value,
                ])
                ->where('options.google', [
                    RegistrationPolicy::Disabled->value,
                    RegistrationPolicy::ApprovalRequired->value,
                    RegistrationPolicy::Open->value,
                    RegistrationPolicy::ExistingOnly->value,
                ])
                ->where('options.household_creation', [
                    HouseholdCreationPolicy::Allowed->value,
                    HouseholdCreationPolicy::Denied->value,
                ]));
    }

    public function test_a_user_without_the_permission_is_forbidden_on_get_and_post(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('admin.registrations.settings'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.registrations.settings.update'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(
            RegistrationPolicy::Disabled,
            app(RegistrationSettings::class)->policyFor(RegistrationChannel::Web),
        );
    }

    public function test_updating_each_setting_persists_it(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.registrations.settings.update'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $settings = app(RegistrationSettings::class);

        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::Open, $settings->policyFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Google));
        $this->assertSame(InvitationPolicy::Disabled, $settings->invitationPolicyFor(RegistrationChannel::HouseholdInvitation));
        $this->assertSame(InvitationPolicy::Enabled, $settings->invitationPolicyFor(RegistrationChannel::AdminInvitation));
        $this->assertSame(HouseholdCreationPolicy::Denied, $settings->householdCreationPolicy());
    }

    public function test_an_invalid_enum_value_is_rejected_and_nothing_is_persisted(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.registrations.settings'))
            ->put(route('admin.registrations.settings.update'), $this->payload([
                'web' => 'not-a-policy',
                'api' => RegistrationPolicy::ExistingOnly->value,
            ]))
            ->assertRedirect(route('admin.registrations.settings'))
            ->assertSessionHasErrors(['web', 'api']);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(
            RegistrationPolicy::Disabled,
            app(RegistrationSettings::class)->policyFor(RegistrationChannel::Api),
        );
    }

    public function test_a_precognitive_request_validates_with_zero_side_effects(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->put(route('admin.registrations.settings.update'), $this->payload([
                'web' => RegistrationPolicy::Open->value,
            ]))
            ->assertSuccessfulPrecognition();

        // The action never ran: nothing was persisted by the precognitive success.
        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(
            RegistrationPolicy::Disabled,
            app(RegistrationSettings::class)->policyFor(RegistrationChannel::Web),
        );

        $this->actingAs($admin)
            ->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->put(route('admin.registrations.settings.update'), $this->payload([
                'web' => 'not-a-policy',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('web');

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(
            RegistrationPolicy::Disabled,
            app(RegistrationSettings::class)->policyFor(RegistrationChannel::Web),
        );
    }

    public function test_disabling_household_invitations_refuses_the_link_and_re_enabling_restores_it(): void
    {
        $owner = User::factory()->create();
        $link = HouseholdInviteLink::create([
            'household_id' => Household::factory()->create(['created_by' => $owner->id])->id,
            'created_by' => $owner->id,
            'token' => 'invite-token-'.Str::random(32),
        ]);

        $this->get(route('invite-links.show', $link->token))->assertOk();

        app(RegistrationSettings::class)->setInvitationPolicy(
            RegistrationChannel::HouseholdInvitation,
            InvitationPolicy::Disabled,
        );

        $this->get(route('invite-links.show', $link->token))->assertNotFound();
        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'email' => 'blocked@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);

        app(RegistrationSettings::class)->setInvitationPolicy(
            RegistrationChannel::HouseholdInvitation,
            InvitationPolicy::Enabled,
        );

        $this->get(route('invite-links.show', $link->token))->assertOk();
    }

    public function test_disabling_admin_invitations_refuses_the_admin_invitation_routes(): void
    {
        $invited = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('invitation.show', now()->addMinutes(30), ['user' => $invited->id]);

        $this->get($url)->assertOk();

        app(RegistrationSettings::class)->setInvitationPolicy(
            RegistrationChannel::AdminInvitation,
            InvitationPolicy::Disabled,
        );

        $this->get($url)->assertNotFound();

        app(RegistrationSettings::class)->setInvitationPolicy(
            RegistrationChannel::AdminInvitation,
            InvitationPolicy::Enabled,
        );

        $this->get($url)->assertOk();
    }

    public function test_the_household_creation_policy_denies_when_allowed_delegates(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->can_create_households_override);
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));

        app(RegistrationSettings::class)->setHouseholdCreationPolicy(HouseholdCreationPolicy::Denied);

        $this->assertFalse(app(PlatformAccess::class)->canCreateHouseholds($user));

        $request = StoreHouseholdRequest::create('/households', 'POST');
        $request->setUserResolver(fn () => $user);
        $this->assertFalse($request->authorize());

        app(RegistrationSettings::class)->setHouseholdCreationPolicy(HouseholdCreationPolicy::Allowed);

        // Allowed keeps delegating: the tri-state override and the entitlement
        // gate still decide.
        $this->assertTrue(app(PlatformAccess::class)->canCreateHouseholds($user));
        $this->assertFalse(
            app(PlatformAccess::class)->canCreateHouseholds(User::factory()->withoutHouseholdCreation()->create()),
        );
    }

    /**
     * Create an approved administrator.
     */
    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Build a valid settings payload, with optional overrides.
     *
     * @param  array<string, string>  $overrides  Field overrides.
     * @return array<string, string> The payload.
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'web' => RegistrationPolicy::ApprovalRequired->value,
            'api' => RegistrationPolicy::Open->value,
            'google' => RegistrationPolicy::ApprovalRequired->value,
            'household_invitation' => InvitationPolicy::Disabled->value,
            'admin_invitation' => InvitationPolicy::Enabled->value,
            'household_creation' => HouseholdCreationPolicy::Denied->value,
        ], $overrides);
    }
}
