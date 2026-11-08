<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\ResolveGoogleIdentity;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\HouseholdInviteLink;
use App\Models\Permission;
use App\Models\RegistrationAudit;
use App\Models\RegistrationBlock;
use App\Models\Role;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationRejected;
use App\Services\Auth\RegistrationSettings;
use App\Support\Registration\RegistrationIdentifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Covers the approval/rejection half of the registration lifecycle: the legal
 * state machine, the persistent block a rejection creates, the release paths,
 * notifications and idempotency.
 */
class RegistrationApprovalLifecycleTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'registration-approval-test-key',
            'registration.block.key_id' => 'v1',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_an_authorised_admin_can_approve_and_the_actor_and_notification_are_recorded(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create(['approval_status' => 'pending']);
        $user->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $user))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(ApprovalStatus::Approved->value, $user->approval_status);
        $this->assertNotNull($user->approved_at);
        $this->assertTrue($user->canAccessApplication(), 'An approved account can complete onboarding.');

        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'approved',
            'new_status' => 'approved',
        ]);

        Notification::assertSentTo($user, RegistrationApproved::class);
    }

    public function test_a_user_without_the_approve_permission_is_forbidden_and_cannot_assign_roles(): void
    {
        $approver = $this->userWithPermissions(['admin.registrations.approve']);
        $assignerOnly = $this->userWithPermissions(['admin.users.assign-roles']);
        $target = User::factory()->create(['approval_status' => 'pending']);

        // The approver cannot assign roles.
        $this->actingAs($approver)
            ->patchJson(route('api.v1.admin.users.update', $target), ['roles' => ['admin']])
            ->assertForbidden();

        // The role assigner cannot approve.
        $this->actingAs($assignerOnly)
            ->post(route('admin.registrations.approve', $target))
            ->assertForbidden();

        $this->assertSame('pending', $target->fresh()->approval_status);
    }

    public function test_reject_sets_the_status_creates_a_block_and_refuses_every_channel(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $email = 'rejected-everywhere@example.com';
        $user = User::factory()->create(['email' => $email, 'approval_status' => 'pending']);

        $this->actingAs($admin)
            ->post(route('admin.registrations.reject', $user), ['reason' => 'Duplicate of an existing account'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(ApprovalStatus::Rejected->value, $user->approval_status);
        $this->assertFalse($user->canAccessApplication());

        $blocks = app(RegistrationBlockRepository::class);
        $this->assertTrue($blocks->isBlocked(RegistrationIdentifier::EMAIL, $email));
        $this->assertSame(RegistrationBlockReason::RejectedRegistration, $blocks->activeBlockFor(RegistrationIdentifier::EMAIL, $email)?->reason_code);
        $this->assertSame('Duplicate of an existing account', $blocks->activeBlockFor(RegistrationIdentifier::EMAIL, $email)?->reason_note);

        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'rejected',
            'new_status' => 'rejected',
        ]);

        Notification::assertSentTo($user, RegistrationRejected::class);

        // Remove the account row so the check that refuses each channel is the
        // BLOCK, not the existing-identity rule.
        $user->forceDelete();

        // The block is checked BEFORE the existing-account check on every channel.
        // Drop the acting administrator: the web registration route is guest-only.
        $this->app['auth']->forgetGuards();

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);
        $this->post(route('register.store'), [
            'name' => 'Rejected Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);
        $this->postJson(route('api.v1.auth.register'), [
            'name' => 'Rejected Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(403)->assertJson(['code' => 'identity_blocked']);

        $google = app(ResolveGoogleIdentity::class)->resolve('google-sub-rejected', $email, 'Rejected', true);
        $this->assertSame(RegistrationDecision::Denied, $google->decision);
        $this->assertSame('identity_blocked', $google->reason);

        $owner = User::factory()->create();
        $owner->assignRole('user');
        $this->createHouseholdForUser($owner);
        $link = HouseholdInviteLink::create([
            'household_id' => $this->household->id,
            'created_by' => $owner->id,
            'token' => 'rejected-token',
        ]);

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Rejected Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_rejected_registration_cannot_be_approved_without_releasing_first(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['approval_status' => 'rejected']);
        $blocks = app(RegistrationBlockRepository::class);
        $blocks->block(
            RegistrationIdentifier::EMAIL,
            $user->email,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
            $admin,
            'Rejected',
        );

        $this->actingAs($admin)
            ->from(route('admin.registrations.index'))
            ->post(route('admin.registrations.approve', $user))
            ->assertRedirect(route('admin.registrations.index'))
            ->assertSessionHasErrors('user');

        $this->assertSame(ApprovalStatus::Rejected->value, $user->fresh()->approval_status);

        // Release alone does NOT approve.
        $this->actingAs($admin)
            ->post(route('admin.registrations.release-rejection', $user))
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(ApprovalStatus::Pending->value, $user->approval_status);
        $this->assertFalse($blocks->isBlocked(RegistrationIdentifier::EMAIL, $user->email));

        // Now the approval succeeds.
        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $user))
            ->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Approved->value, $user->fresh()->approval_status);
    }

    public function test_releasing_a_rejection_is_audited_and_allows_a_fresh_registration(): void
    {
        $admin = $this->admin();
        $email = 'released@example.com';
        $user = User::factory()->create(['email' => $email, 'approval_status' => 'pending']);

        $this->actingAs($admin)->post(route('admin.registrations.reject', $user), ['reason' => 'Mistake']);
        $this->actingAs($admin)->post(route('admin.registrations.release-rejection', $user))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'rejection_released',
            'new_status' => 'pending',
        ]);

        $this->assertFalse(app(RegistrationBlockRepository::class)->isBlocked(RegistrationIdentifier::EMAIL, $email));

        // With the prohibition lifted, the gate applies again; an open web
        // policy lets the address register once the existing row is gone.
        $user->forceDelete();
        $this->app['auth']->forgetGuards();
        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Open);

        $this->post(route('register.store'), [
            'name' => 'New Person',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => $email, 'approval_status' => 'approved']);
    }

    public function test_an_authorised_admin_can_release_a_block_and_it_is_audited(): void
    {
        $admin = $this->admin();
        $blocks = app(RegistrationBlockRepository::class);
        $block = $blocks->block(
            RegistrationIdentifier::EMAIL,
            'blocked@example.com',
            RegistrationBlockReason::ManualBlock,
            RegistrationBlockSource::Admin,
            $admin,
            'Manual block',
        );

        $this->actingAs($admin)
            ->post(route('admin.registration-blocks.release', $block))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($blocks->isBlocked(RegistrationIdentifier::EMAIL, 'blocked@example.com'));
        $this->assertDatabaseHas('registration_audits', [
            'actor_id' => $admin->id,
            'action' => 'block_released',
        ]);
    }

    public function test_repeating_approve_and_reject_does_not_duplicate_the_audit_or_the_block(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = User::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($admin)->post(route('admin.registrations.approve', $user));
        $this->actingAs($admin)->post(route('admin.registrations.approve', $user));

        $this->assertSame(
            1,
            RegistrationAudit::query()->where('user_id', $user->id)->where('action', 'approved')->count(),
        );

        $other = User::factory()->create(['approval_status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.registrations.reject', $other), ['reason' => 'Duplicate']);
        $this->actingAs($admin)->post(route('admin.registrations.reject', $other), ['reason' => 'Duplicate']);

        $this->assertSame(
            1,
            RegistrationAudit::query()->where('user_id', $other->id)->where('action', 'rejected')->count(),
        );
        $this->assertSame(1, RegistrationBlock::query()->active()->where('reason_note', 'Duplicate')->count());
    }

    public function test_a_user_without_the_permission_cannot_approve_reject_or_release(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $target = User::factory()->create(['approval_status' => 'pending']);

        $this->actingAs($outsider)->post(route('admin.registrations.approve', $target))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.registrations.reject', $target), ['reason' => 'no'])->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.registrations.release-rejection', $target))->assertForbidden();

        $block = app(RegistrationBlockRepository::class)->block(
            RegistrationIdentifier::EMAIL,
            'outsider-target@example.com',
            RegistrationBlockReason::ManualBlock,
            RegistrationBlockSource::Admin,
        );
        $this->actingAs($outsider)->post(route('admin.registration-blocks.release', $block))->assertForbidden();

        $this->assertSame('pending', $target->fresh()->approval_status);
    }

    /**
     * Create an approved platform administrator with every permission.
     */
    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * Create a user holding exactly the named route permissions.
     *
     * @param  list<string>  $routeNames
     */
    private function userWithPermissions(array $routeNames): User
    {
        $role = Role::create(['name' => 'Test role '.Str::random(6), 'slug' => 'test-role-'.Str::random(8)]);
        $role->syncPermissions(Permission::query()->whereIn('route_name', $routeNames)->get());
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
