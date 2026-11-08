<?php

namespace Tests\Feature\Registration;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the account-status axis and its effect on effective access.
 */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_defaults_to_active(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AccountStatus::Active, $user->account_status);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'account_status' => 'active']);
    }

    public function test_allows_access_only_for_the_active_state(): void
    {
        $this->assertTrue(AccountStatus::Active->allowsAccess());
        $this->assertFalse(AccountStatus::Suspended->allowsAccess());
        $this->assertFalse(AccountStatus::Blocked->allowsAccess());
    }

    public function test_application_access_requires_approved_and_active(): void
    {
        $approvedActive = User::factory()->create(['approval_status' => 'approved']);
        $this->assertTrue($approvedActive->canAccessApplication());

        $pending = User::factory()->create(['approval_status' => 'pending']);
        $this->assertFalse($pending->canAccessApplication());

        $suspended = User::factory()->suspended()->create();
        $this->assertFalse($suspended->canAccessApplication());

        $blocked = User::factory()->blocked()->create();
        $this->assertFalse($blocked->canAccessApplication());
    }

    public function test_the_two_axes_are_independent(): void
    {
        $approvedBlocked = User::factory()->blocked()->create(['approval_status' => 'approved']);
        $this->assertSame('approved', $approvedBlocked->approval_status);
        $this->assertSame(AccountStatus::Blocked, $approvedBlocked->account_status);
        $this->assertFalse($approvedBlocked->canAccessApplication());

        $pendingActive = User::factory()->create([
            'approval_status' => 'pending',
            'account_status' => AccountStatus::Active,
        ]);
        $this->assertTrue($pendingActive->account_status->allowsAccess());
        $this->assertFalse($pendingActive->canAccessApplication());
    }

    public function test_a_suspended_administrator_no_longer_counts_as_an_administrator(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->assertTrue(app(PlatformAccess::class)->isAdministrator($admin));

        $admin->update(['account_status' => AccountStatus::Suspended]);

        $this->assertFalse(app(PlatformAccess::class)->isAdministrator($admin->refresh()));
    }
}
