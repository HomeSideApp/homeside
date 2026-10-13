<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\RegisterAccount;
use App\Actions\Auth\ResolveGoogleIdentity;
use App\Contracts\RegistrationBlockRepository;
use App\Data\Auth\InvitationContextData;
use App\Data\Auth\RegisterAccountData;
use App\Enums\ApprovalStatus;
use App\Enums\InvitationPolicy;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\AuthenticationBootstrap;
use App\Models\HouseholdInviteLink;
use App\Models\RegistrationAudit;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InviteUserNotification;
use App\Services\Auth\RegistrationSettings;
use App\Support\Registration\RegistrationIdentifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Covers the four registration channels end to end.
 *
 * Each channel must route through the shared RegisterAccount use case, honour
 * its policy, refuse a blocked identity and never overwrite an existing
 * account. The audit trail is used as evidence that the shared use case ran,
 * because it is written in exactly one place.
 */
class RegistrationChannelsTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'registration-channel-test-key',
            'registration.block.key_id' => 'v1',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('user');
        $this->createHouseholdForUser($this->owner);
    }

    /** @return array<string, string> */
    private function webPayload(string $email): array
    {
        return [
            'name' => 'Web Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    /** @return array<string, string> */
    private function apiPayload(string $email): array
    {
        return [
            'name' => 'API Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function link(string $token = 'channel-token', ?int $maxUses = null): HouseholdInviteLink
    {
        return HouseholdInviteLink::create([
            'household_id' => $this->household->id,
            'created_by' => $this->owner->id,
            'token' => $token,
            'max_uses' => $maxUses,
        ]);
    }

    private function block(string $email): void
    {
        app(RegistrationBlockRepository::class)->block(
            RegistrationIdentifier::EMAIL,
            $email,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
        );
    }

    public function test_web_registration_follows_the_channel_policy(): void
    {
        Notification::fake();
        $settings = app(RegistrationSettings::class);

        // Open: creates an approved account, records the audit entry and hands
        // out a single-use enrolment bootstrap instead of a dead-end /login.
        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);
        $openResponse = $this->post(route('register.store'), $this->webPayload('web-open@example.com'));
        $openResponse->assertRedirect();
        $this->assertStringContainsString('auth/bootstrap', (string) $openResponse->headers->get('Location'));

        $open = User::where('email', 'web-open@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Approved->value, $open->approval_status);
        $this->assertTrue($open->hasRole('user'));
        Notification::assertSentTo($open, InviteUserNotification::class);
        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $open->id,
            'action' => 'registration_created',
            'channel' => RegistrationChannel::Web->value,
        ]);
        $this->assertSame(1, AuthenticationBootstrap::query()
            ->where('user_id', $open->id)
            ->where('purpose', 'two_factor_enrolment')
            ->count());

        // Approval required: creates a pending account and must NOT hand out a
        // usable enrolment path into a session.
        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::ApprovalRequired);
        $this->post(route('register.store'), $this->webPayload('web-pending@example.com'))->assertRedirect(route('login'));
        $pending = User::where('email', 'web-pending@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Pending->value, $pending->approval_status);
        $this->assertSame(0, AuthenticationBootstrap::query()->where('user_id', $pending->id)->count());

        // Disabled: nothing is created.
        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Disabled);
        $this->post(route('register.store'), $this->webPayload('web-disabled@example.com'))->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'web-disabled@example.com']);
    }

    public function test_api_registration_follows_the_channel_policy(): void
    {
        Notification::fake();
        $settings = app(RegistrationSettings::class);

        $settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);
        $this->postJson(route('api.v1.auth.register'), $this->apiPayload('api-open@example.com'))->assertCreated();
        $open = User::where('email', 'api-open@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Approved->value, $open->approval_status);
        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $open->id,
            'action' => 'registration_created',
            'channel' => RegistrationChannel::Api->value,
        ]);

        $settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);
        $response = $this->postJson(route('api.v1.auth.register'), $this->apiPayload('api-pending@example.com'));
        $response->assertCreated()->assertJson(['state' => 'approval_pending']);
        $this->assertNull($response->json('token'));
        $this->assertNull($response->json('data.token'));
        $pending = User::where('email', 'api-pending@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Pending->value, $pending->approval_status);
        $this->assertSame(0, $pending->tokens()->count());

        $settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Disabled);
        $this->postJson(route('api.v1.auth.register'), $this->apiPayload('api-disabled@example.com'))
            ->assertStatus(403)
            ->assertJson(['code' => 'registration_disabled']);
        $this->assertDatabaseMissing('users', ['email' => 'api-disabled@example.com']);
    }

    public function test_a_pending_account_cannot_authenticate_after_registration(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);

        $this->postJson(route('api.v1.auth.register'), $this->apiPayload('pending-login@example.com'))->assertCreated();

        $this->postJson(route('api.v1.auth.login'), [
            'email' => 'pending-login@example.com',
            'password' => 'password123',
        ])->assertForbidden();

        $this->assertGuest();
        $this->assertSame(0, User::where('email', 'pending-login@example.com')->firstOrFail()->tokens()->count());
    }

    public function test_household_invitation_registration_records_the_channel_and_respects_the_policy(): void
    {
        $link = $this->link();

        $this->post(route('invite-links.store', $link->token), [
            ...$this->webPayload('invite-open@example.com'),
            'token' => $link->token,
        ])->assertRedirect();

        $user = User::where('email', 'invite-open@example.com')->firstOrFail();
        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'action' => 'registration_created',
            'channel' => RegistrationChannel::HouseholdInvitation->value,
        ]);
        $this->assertDatabaseHas('household_members', ['household_id' => $this->household->id, 'user_id' => $user->id]);

        app(RegistrationSettings::class)->setInvitationPolicy(RegistrationChannel::HouseholdInvitation, InvitationPolicy::Disabled);

        $disabledLink = $this->link('disabled-token');
        $this->post(route('invite-links.store', $disabledLink->token), [
            ...$this->webPayload('invite-disabled@example.com'),
            'token' => $disabledLink->token,
        ])->assertNotFound();
        $this->assertDatabaseMissing('users', ['email' => 'invite-disabled@example.com']);
    }

    public function test_admin_invitation_completes_the_pre_created_account_without_recreating_it(): void
    {
        $invited = User::factory()->unverified()->create(['email' => 'invited-admin@example.com']);
        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));

        $this->post($url, [
            'token' => $invited->getRouteKey(),
            'name' => 'Invited Admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $invited->refresh();
        $this->assertNotNull($invited->email_verified_at);
        $this->assertSame('Invited Admin', $invited->name);
        $this->assertTrue(Hash::check('password123', $invited->password));
        $this->assertSame(1, User::query()->where('email', 'invited-admin@example.com')->count());
        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $invited->id,
            'action' => 'registration_created',
            'channel' => RegistrationChannel::AdminInvitation->value,
        ]);

        // The channel policy disables the whole flow.
        app(RegistrationSettings::class)->setInvitationPolicy(RegistrationChannel::AdminInvitation, InvitationPolicy::Disabled);
        $other = User::factory()->unverified()->create();
        $disabledUrl = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));

        $this->post($disabledUrl, [
            'token' => $other->getRouteKey(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_a_blocked_identity_is_refused_on_every_channel(): void
    {
        $email = 'blocked-everywhere@example.com';
        $this->block($email);

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);
        $this->post(route('register.store'), $this->webPayload($email))->assertSessionHasErrors('email');

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);
        $this->postJson(route('api.v1.auth.register'), $this->apiPayload($email))
            ->assertStatus(403)
            ->assertJson(['code' => 'identity_blocked']);

        $link = $this->link('blocked-token');
        $this->post(route('invite-links.store', $link->token), [
            ...$this->webPayload($email),
            'token' => $link->token,
        ])->assertSessionHasErrors('email');
        $this->assertSame(0, $link->fresh()->uses_count);

        $invited = User::factory()->unverified()->create(['email' => $email]);
        $url = URL::temporarySignedRoute('invitation.store', now()->addMinutes(30));
        $this->post($url, [
            'token' => $invited->getRouteKey(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');
        $this->assertNull($invited->fresh()->email_verified_at);

        $this->assertSame(0, RegistrationAudit::query()->where('action', 'registration_created')->count());
    }

    public function test_an_existing_account_is_never_overwritten_by_an_invitation(): void
    {
        $existing = User::factory()->create([
            'email' => 'taken@example.com',
            'password' => Hash::make('original-password'),
        ]);
        $originalName = $existing->name;

        $link = $this->link('existing-token');
        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Impostor',
            'email' => 'taken@example.com',
            'password' => 'attacker-password',
            'password_confirmation' => 'attacker-password',
        ])->assertSessionHasErrors('email');

        $existing->refresh();
        $this->assertTrue(Hash::check('original-password', $existing->password));
        $this->assertSame($originalName, $existing->name);
        $this->assertSame(0, $link->fresh()->uses_count);
        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());

        // And the use case itself refuses to write, even without request validation.
        $outcome = app(RegisterAccount::class)->execute(new RegisterAccountData(
            name: 'Impostor',
            email: 'taken@example.com',
            password: 'attacker-password',
            locale: 'en-US',
            channel: RegistrationChannel::HouseholdInvitation,
            invitation: new InvitationContextData(reference: 'direct'),
            origin: 'test',
        ));

        $this->assertSame(RegistrationDecision::ExistingIdentity, $outcome->decision);
        $this->assertTrue(Hash::check('original-password', $existing->fresh()->password));
    }

    public function test_google_never_links_by_email_match_and_honours_the_policy(): void
    {
        $existing = User::factory()->create(['email' => 'google-existing@example.com']);

        $outcome = app(ResolveGoogleIdentity::class)
            ->resolve('google-sub-link', 'google-existing@example.com', 'Existing', true);

        $this->assertSame(RegistrationDecision::LinkRequired, $outcome->decision);
        $this->assertDatabaseMissing('google_identities', ['google_sub' => 'google-sub-link']);
        $this->assertSame(1, User::query()->where('email', 'google-existing@example.com')->count());

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::ExistingOnly);
        $denied = app(ResolveGoogleIdentity::class)
            ->resolve('google-sub-new', 'google-new@example.com', 'New', true);
        $this->assertSame(RegistrationDecision::Denied, $denied->decision);
        $this->assertDatabaseMissing('users', ['email' => 'google-new@example.com']);

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Open);
        $created = app(ResolveGoogleIdentity::class)
            ->resolve('google-sub-open', 'google-open@example.com', 'Open', true);
        $this->assertTrue($created->registered());
        $this->assertTrue($created->user->hasRole('user'));
        $this->assertDatabaseHas('google_identities', ['google_sub' => 'google-sub-open']);
        $this->assertDatabaseHas('registration_audits', ['channel' => RegistrationChannel::Google->value]);

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Disabled);
        $disabled = app(ResolveGoogleIdentity::class)
            ->resolve('google-sub-disabled', 'google-disabled@example.com', 'Disabled', true);
        $this->assertSame(RegistrationDecision::Denied, $disabled->decision);
        $this->assertDatabaseMissing('users', ['email' => 'google-disabled@example.com']);
    }

    public function test_a_blocked_google_identity_never_creates_an_account(): void
    {
        $this->block('google-blocked@example.com');

        $outcome = app(ResolveGoogleIdentity::class)
            ->resolve('google-sub-blocked', 'google-blocked@example.com', 'Blocked', true);

        $this->assertSame(RegistrationDecision::Denied, $outcome->decision);
        $this->assertSame('identity_blocked', $outcome->reason);
        $this->assertDatabaseMissing('users', ['email' => 'google-blocked@example.com']);
        $this->assertDatabaseMissing('google_identities', ['google_sub' => 'google-sub-blocked']);
    }

    public function test_google_open_policy_creates_an_approved_user_that_must_enrol_two_factor(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::Open);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-web-open-sub',
            'email' => 'google-web-open@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('google.otp.setup'));

        $user = User::where('email', 'google-web-open@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Approved->value, $user->approval_status);
        $this->assertTrue($user->hasRole('user'));
        $this->assertGuest();
    }

    public function test_the_web_registration_page_reflects_the_public_capabilities(): void
    {
        $settings = app(RegistrationSettings::class);
        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::ApprovalRequired);

        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/Register')
            ->where('capabilities.web.available', true)
            ->where('capabilities.web.approval_required', true)
        );

        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('capabilities.web.available', true)
            ->where('capabilities.web.approval_required', false)
        );

        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Disabled);

        $this->get(route('register'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('capabilities.web.available', false)
        );
    }

    public function test_the_api_capabilities_endpoint_publishes_the_policies(): void
    {
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::ApprovalRequired);

        $this->getJson(route('api.v1.auth.registration'))->assertOk()
            ->assertJsonPath('data.channels.api.available', true)
            ->assertJsonPath('data.channels.api.policy', RegistrationPolicy::ApprovalRequired->value)
            ->assertJsonPath('data.channels.api.approval_required', true)
            ->assertJsonPath('data.channels.web.policy', RegistrationPolicy::Disabled->value);
    }
}
