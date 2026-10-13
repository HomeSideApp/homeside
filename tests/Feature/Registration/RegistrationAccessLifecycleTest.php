<?php

namespace Tests\Feature\Registration;

use App\Actions\Users\RevokeUserAccess;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Enums\RegistrationBlockReason;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\Household;
use App\Models\Permission;
use App\Models\RegistrationAudit;
use App\Models\RegistrationBlock;
use App\Models\Role;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Covers the account lifecycle: suspension, blocking, deletion, access
 * revocation, the global account-status middleware and the
 * last-administrator safeguard.
 */
class RegistrationAccessLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'registration.block.key' => 'registration-access-test-key',
            'registration.block.key_id' => 'v1',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_the_account_middleware_is_attached_to_protected_routes_but_not_the_login_routes(): void
    {
        $this->assertContains('account.active', Route::getRoutes()->getByName('dashboard')->gatherMiddleware());
        $this->assertNotContains('account.active', Route::getRoutes()->getByName('login')->gatherMiddleware());
        $this->assertNotContains('account.active', Route::getRoutes()->getByName('register')->gatherMiddleware());
    }

    public function test_the_middleware_refuses_suspended_blocked_and_rejected_accounts(): void
    {
        $middleware = app(EnsureAccountIsActive::class);

        $denied = [
            User::factory()->suspended()->create(),
            User::factory()->blocked()->create(),
            User::factory()->create(['approval_status' => 'rejected']),
        ];

        foreach ($denied as $user) {
            $request = Request::create('/dashboard', 'GET');
            $request->setUserResolver(fn () => $user);

            try {
                $middleware->handle($request, fn () => response('ok'));
                $this->fail('The middleware must refuse a '.$user->account_status->value.'/'.$user->approval_status.' account.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $active = User::factory()->create();
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $active);
        $this->assertSame('ok', $middleware->handle($request, fn () => response('ok'))->getContent());
    }

    public function test_suspend_sets_the_status_and_revokes_tokens_and_an_open_session_loses_access(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('user');
        $target->createToken('auth-token');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $target))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(AccountStatus::Suspended, $target->account_status);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertFalse($target->canAccessApplication());

        $this->assertDatabaseHas('registration_audits', [
            'user_id' => $target->id,
            'actor_id' => $admin->id,
            'action' => 'suspended',
        ]);

        // An open session (simulated by actingAs) loses a protected route at once.
        $this->actingAs($target)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_block_sets_the_status_and_persists_an_identity_block(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('user');

        $this->actingAs($admin)
            ->post(route('admin.users.block', $target), ['reason' => 'Abuse'])
            ->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame(AccountStatus::Blocked, $target->account_status);

        $block = app(RegistrationBlockRepository::class)->activeBlockFor(RegistrationIdentifier::EMAIL, $target->email);
        $this->assertNotNull($block);
        $this->assertSame(RegistrationBlockReason::ManualBlock, $block->reason_code);
        $this->assertSame('Abuse', $block->reason_note);

        // Unblocking lifts both the account status and the manual identity block.
        $this->actingAs($admin)
            ->post(route('admin.users.unblock', $target))
            ->assertSessionHasNoErrors();

        $this->assertSame(AccountStatus::Active, $target->fresh()->account_status);
        $this->assertFalse(app(RegistrationBlockRepository::class)->isBlocked(RegistrationIdentifier::EMAIL, $target->email));
    }

    public function test_delete_without_a_block_leaves_the_identity_free(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'delete-free@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.users.delete', $target))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertFalse(app(RegistrationBlockRepository::class)->isBlocked(RegistrationIdentifier::EMAIL, 'delete-free@example.com'));
        $this->assertDatabaseHas('registration_audits', [
            'actor_id' => $admin->id,
            'action' => 'deleted',
        ]);

        // Repeating the deletion is a documented 404, not a second audit row.
        $this->actingAs($admin)
            ->delete(route('admin.users.delete', $target))
            ->assertNotFound();

        $this->assertSame(1, RegistrationAudit::query()->where('action', 'deleted')->count());
    }

    public function test_delete_with_a_block_forbids_the_identity_from_coming_back(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'delete-blocked@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.users.delete-and-block', $target), ['reason' => 'Fraud'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $blocks = app(RegistrationBlockRepository::class);
        $this->assertTrue($blocks->isBlocked(RegistrationIdentifier::EMAIL, 'delete-blocked@example.com'));
        $this->assertSame(RegistrationBlockReason::DeletedAndBlocked, $blocks->activeBlockFor(RegistrationIdentifier::EMAIL, 'delete-blocked@example.com')?->reason_code);
        $this->assertDatabaseHas('registration_audits', [
            'actor_id' => $admin->id,
            'action' => 'deleted_and_blocked',
        ]);
    }

    public function test_delete_and_block_cannot_be_used_with_only_the_delete_permission(): void
    {
        $actor = $this->userWithPermissions(['admin.users.delete']);
        $target = User::factory()->create();

        // The dedicated route requires the stronger permission.
        $this->actingAs($actor)
            ->delete(route('admin.users.delete-and-block', $target))
            ->assertForbidden();

        // Nor can the flag be smuggled through the plain delete route.
        $this->actingAs($actor)
            ->delete(route('admin.users.delete', $target), ['alsoBlock' => true])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseCount('registration_blocks', 0);
    }

    public function test_a_user_who_owns_a_household_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $owner = User::factory()->create();
        Household::factory()->create(['created_by' => $owner->id]);

        $this->actingAs($admin)
            ->from(route('admin.users'))
            ->delete(route('admin.users.delete', $owner))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_the_last_administrator_safeguard_refuses_to_orphan_the_platform(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.users.delete', $admin))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $admin))
            ->assertForbidden();

        $this->assertSame(AccountStatus::Active, $admin->fresh()->account_status);
    }

    public function test_a_user_without_the_lifecycle_permission_cannot_act_on_another_user(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $target = User::factory()->create();

        $this->actingAs($outsider)->post(route('admin.users.suspend', $target))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.users.block', $target))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.users.unblock', $target))->assertForbidden();
        $this->actingAs($outsider)->post(route('admin.users.unsuspend', $target))->assertForbidden();
        $this->actingAs($outsider)->delete(route('admin.users.delete', $target))->assertForbidden();

        $this->assertSame(AccountStatus::Active, $target->fresh()->account_status);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_repeating_suspend_and_block_does_not_duplicate_the_audit_or_the_block(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.suspend', $target));
        $this->actingAs($admin)->post(route('admin.users.suspend', $target));

        $this->assertSame(1, RegistrationAudit::query()->where('user_id', $target->id)->where('action', 'suspended')->count());

        $this->actingAs($admin)->post(route('admin.users.block', $target), ['reason' => 'Abuse']);
        $this->actingAs($admin)->post(route('admin.users.block', $target), ['reason' => 'Abuse']);

        $this->assertSame(1, RegistrationAudit::query()->where('user_id', $target->id)->where('action', 'blocked')->count());
        $this->assertSame(1, RegistrationBlock::query()->active()->count());
    }

    public function test_revoke_user_access_deletes_tokens_and_database_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('auth-token');

        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        app(RevokeUserAccess::class)->execute($user);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertNull($user->fresh()->remember_token);
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
