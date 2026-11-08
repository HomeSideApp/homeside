<?php

namespace Tests\Feature\Admin;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Enums\RegistrationChannel;
use App\Models\RegistrationAudit;
use App\Models\RegistrationBlock;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the "Registration & Access" admin panel: authorization, the three
 * queues and their filters, and Precognition with no side effects.
 */
class RegistrationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'registration-panel-test-key',
            'registration.block.key_id' => 'v1',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_admin_panel_lists_pending_rejected_and_blocked_entries(): void
    {
        $admin = $this->admin();

        $pending = User::factory()->unverified()->create(['approval_status' => 'pending']);
        RegistrationAudit::create([
            'user_id' => $pending->id,
            'action' => RegistrationAuditAction::RegistrationCreated,
            'new_status' => 'pending',
            'channel' => RegistrationChannel::Web->value,
        ]);

        $rejected = User::factory()->create(['approval_status' => 'rejected']);
        RegistrationAudit::create([
            'user_id' => $rejected->id,
            'action' => RegistrationAuditAction::RegistrationCreated,
            'new_status' => 'pending',
            'channel' => RegistrationChannel::Api->value,
        ]);
        app(RegistrationBlockRepository::class)->block(
            RegistrationIdentifier::EMAIL,
            $rejected->email,
            RegistrationBlockReason::RejectedRegistration,
            RegistrationBlockSource::Admin,
            $admin,
            'Rejected for review',
        );

        $this->actingAs($admin)
            ->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('admin/registrations/Index')
                ->has('pending', 1)
                ->where('pending.0.email', $pending->email)
                ->where('pending.0.channel', RegistrationChannel::Web->value)
                ->where('pending.0.email_verified', false)
                ->has('rejected', 1)
                ->where('rejected.0.email', $rejected->email)
                ->where('rejected.0.reason', 'Rejected for review')
                ->where('rejected.0.rejected_by', $admin->name)
                ->has('blocks', 1)
                ->where('blocks.0.reason_code', RegistrationBlockReason::RejectedRegistration->value)
                ->has('options.channels'));
    }

    public function test_the_pending_queue_filters_by_channel(): void
    {
        $admin = $this->admin();

        $web = User::factory()->create(['approval_status' => 'pending']);
        RegistrationAudit::create([
            'user_id' => $web->id,
            'action' => RegistrationAuditAction::RegistrationCreated,
            'new_status' => 'pending',
            'channel' => RegistrationChannel::Web->value,
        ]);

        $api = User::factory()->create(['approval_status' => 'pending']);
        RegistrationAudit::create([
            'user_id' => $api->id,
            'action' => RegistrationAuditAction::RegistrationCreated,
            'new_status' => 'pending',
            'channel' => RegistrationChannel::Api->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.registrations.index', ['channel' => RegistrationChannel::Api->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('pending', 1)
                ->where('pending.0.email', $api->email));
    }

    public function test_a_user_without_the_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get(route('admin.registrations.index'))->assertForbidden();
    }

    public function test_rejection_uses_precognition_with_zero_side_effects(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['approval_status' => 'pending']);

        // An invalid reason is refused by the precognitive request...
        $this->actingAs($admin)
            ->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('admin.registrations.reject', $target), ['reason' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        // ...and a valid one succeeds without writing anything.
        $this->actingAs($admin)
            ->withPrecognition()
            ->withHeader('Accept', 'application/json')
            ->post(route('admin.registrations.reject', $target), ['reason' => 'A valid reason'])
            ->assertSuccessfulPrecognition();

        $this->assertSame('pending', $target->fresh()->approval_status);
        $this->assertSame(0, RegistrationBlock::query()->count());
        $this->assertSame(0, RegistrationAudit::query()->count());
    }

    public function test_a_lost_race_surfaces_a_clear_error_not_a_stack_trace(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['approval_status' => 'rejected']);

        $this->actingAs($admin)
            ->from(route('admin.registrations.index'))
            ->post(route('admin.registrations.approve', $target))
            ->assertRedirect(route('admin.registrations.index'))
            ->assertSessionHasErrors('user');

        $this->assertSame('rejected', $target->fresh()->approval_status);
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
}
