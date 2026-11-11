<?php

namespace Tests\Feature\Registration;

use App\Actions\Auth\ApproveRegistration;
use App\Actions\Auth\ResolveGoogleIdentity;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\RegistrationAudit;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Pins the invariant the registrations panel depends on: approving a pending
 * Google registration must make the account USABLE.
 *
 * A Google account created under an `approval_required` policy is stored with
 * `approval_status = pending` and no role, because the administrator is the one
 * who chooses the roles. Approving it from the Registration & Access panel used
 * to change only the status, leaving the account refused by
 * `canAccessApplication()` (a Google-linked account with no role is denied) with
 * nothing on screen explaining why. Approval must therefore confer the baseline
 * `user` role when the account holds no role at all.
 *
 * The privilege separation is untouched: approving never confers a PRIVILEGED
 * role, and an administrator who deliberately assigned roles before approving
 * keeps exactly those roles.
 */
class GoogleApprovalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'google-approval-access-test-key',
            'registration.block.key_id' => 'v1',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        app(RegistrationSettings::class)->setPolicy(RegistrationChannel::Google, RegistrationPolicy::ApprovalRequired);
    }

    public function test_approving_a_pending_google_registration_confers_access_and_allows_authentication(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-approval-access@example.com', 'google-sub-approval-access');

        $this->assertFalse($user->canAccessApplication(), 'A pending Google account must not have access yet.');

        $applied = app(ApproveRegistration::class)->execute($user, $admin);
        $this->assertTrue($applied);

        $user->refresh();
        $this->assertSame(ApprovalStatus::Approved->value, $user->approval_status);
        $this->assertTrue($user->hasRole('user'), 'Approval must confer the baseline `user` role.');
        $this->assertTrue($user->canAccessApplication(), 'An approved Google account must be usable.');

        // The audited approval records that it conferred the baseline role.
        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'actor_id' => $admin->id,
            'action' => 'approved',
            'new_status' => 'approved',
            'origin' => 'admin+default-role:user',
        ]);

        // The same identity can now start authenticating with Google instead of
        // being refused for holding no role. Google is the second factor for a
        // delegated Google sign-in, so approval alone opens the session.
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-sub-approval-access',
            'email' => 'google-approval-access@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_approving_does_not_touch_a_privileged_role_assigned_before_approval(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-privileged@example.com', 'google-sub-privileged');
        $user->assignRole('admin');

        app(ApproveRegistration::class)->execute($user, $admin);

        $user->refresh();
        $this->assertTrue($user->hasRole('admin'), 'The deliberately assigned privileged role is preserved.');
        $this->assertFalse($user->hasRole('user'), 'An account that already holds a role is not given the baseline role.');
        $this->assertSame(['admin'], $user->roles()->pluck('slug')->sort()->values()->all());
        $this->assertTrue($user->canAccessApplication());

        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $user->id,
            'action' => 'approved',
            'origin' => 'admin',
        ]);
    }

    public function test_re_approving_is_idempotent_and_does_not_duplicate_the_role_or_the_audit(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-idempotent@example.com', 'google-sub-idempotent');

        $this->assertTrue(app(ApproveRegistration::class)->execute($user, $admin));
        $this->assertFalse(app(ApproveRegistration::class)->execute($user->refresh(), $admin));

        $this->assertSame(1, RegistrationAudit::query()->where('user_id', $user->id)->where('action', 'approved')->count());
        $this->assertSame(1, $user->roles()->count());
        $this->assertTrue($user->canAccessApplication());
    }

    public function test_approval_fails_loudly_when_the_baseline_role_row_is_missing(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-no-baseline@example.com', 'google-sub-no-baseline');

        Role::query()->where('slug', 'user')->delete();

        try {
            app(ApproveRegistration::class)->execute($user, $admin);
            $this->fail('Approval must not silently approve an account that cannot hold the baseline role.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }

        $user->refresh();
        $this->assertSame(ApprovalStatus::Pending->value, $user->approval_status, 'A failed approval must not change the status.');
        $this->assertFalse($user->canAccessApplication());
        $this->assertSame(0, RegistrationAudit::query()->where('user_id', $user->id)->where('action', 'approved')->count());
    }

    public function test_the_registrations_panel_approve_route_confers_the_baseline_role(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-panel-approve@example.com', 'google-sub-panel-approve');

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', $user))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->hasRole('user'));
        $this->assertTrue($user->canAccessApplication());
    }

    public function test_the_registrations_panel_surfaces_a_clear_error_when_the_baseline_role_is_missing(): void
    {
        $admin = $this->admin();
        $user = $this->pendingGoogleUser('google-panel-missing-role@example.com', 'google-sub-panel-missing-role');

        Role::query()->where('slug', 'user')->delete();

        $this->actingAs($admin)
            ->from(route('admin.registrations.index'))
            ->post(route('admin.registrations.approve', $user))
            ->assertRedirect(route('admin.registrations.index'))
            ->assertSessionHasErrors('user');

        $this->assertSame(ApprovalStatus::Pending->value, $user->fresh()->approval_status);
    }

    /**
     * Create a Google-linked account that waits for approval, exactly as the
     * real `/auth/google` flow does under an `approval_required` policy.
     */
    private function pendingGoogleUser(string $email, string $subject): User
    {
        $outcome = app(ResolveGoogleIdentity::class)->resolve($subject, $email, 'Google Applicant', true);

        $this->assertSame(RegistrationDecision::ApprovalRequired, $outcome->decision);
        $this->assertNotNull($outcome->user);

        $user = $outcome->user->refresh();

        $this->assertSame(ApprovalStatus::Pending->value, $user->approval_status);
        $this->assertTrue($user->googleIdentity()->exists());
        $this->assertTrue($user->roles()->doesntExist(), 'A pending Google account is created without roles.');

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }
}
