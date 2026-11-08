<?php

namespace Tests\Feature\Registration;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Proves the separation the spec demands: approving a registration and
 * assigning roles are different privileges, and the last-administrator
 * safeguard covers the new lifecycle abilities.
 */
class RegistrationPermissionSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_approving_a_registration_does_not_grant_role_assignment(): void
    {
        $approver = $this->userWithPermissions([
            'admin.users.update',
            'admin.registrations.approve',
        ]);
        $target = User::factory()->create(['approval_status' => 'pending']);

        $this->assertTrue(Gate::forUser($approver)->allows('registrations.approve', $target));
        $this->assertFalse($approver->getPermissionRouteNames()->contains('admin.users.assign-roles'));
        $this->assertFalse(app(PlatformAccess::class)->canManage($approver));

        $this->actingAs($approver)
            ->patchJson(route('api.v1.admin.users.update', $target), ['roles' => ['admin']])
            ->assertForbidden();

        $this->assertFalse($target->refresh()->hasRole('admin'));
    }

    public function test_assigning_roles_does_not_grant_registration_approval(): void
    {
        $assigner = $this->userWithPermissions(['admin.users.assign-roles']);
        $target = User::factory()->create(['approval_status' => 'pending']);

        $this->assertTrue(app(PlatformAccess::class)->canManage($assigner));
        $this->assertFalse(Gate::forUser($assigner)->allows('registrations.approve', $target));
        $this->assertFalse(Gate::forUser($assigner)->allows('registrations.reject', $target));
        $this->assertFalse(Gate::forUser($assigner)->allows('registrations.release-rejection', $target));
    }

    public function test_registration_abilities_require_their_own_permissions(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($actor)->allows('registrations.view'));
        $this->assertFalse(Gate::forUser($actor)->allows('registrations.approve', $target));
        $this->assertFalse(Gate::forUser($actor)->allows('registrations.manage-settings'));
    }

    public function test_the_last_administrator_safeguard_covers_the_lifecycle_abilities(): void
    {
        $soleAdmin = $this->userWithPermissions([
            'admin.users.assign-roles',
            'admin.users.suspend',
            'admin.users.block',
            'admin.users.unblock',
            'admin.users.delete',
            'admin.users.delete-and-block',
        ]);
        $other = User::factory()->create();

        $this->assertFalse(Gate::forUser($soleAdmin)->allows('suspend', $soleAdmin));
        $this->assertFalse(Gate::forUser($soleAdmin)->allows('block', $soleAdmin));
        $this->assertFalse(Gate::forUser($soleAdmin)->allows('delete', $soleAdmin));
        $this->assertFalse(Gate::forUser($soleAdmin)->allows('delete-and-block', $soleAdmin));

        // Acting on a non-administrator is allowed while the actor keeps the platform managed.
        $this->assertTrue(Gate::forUser($soleAdmin)->allows('suspend', $other));
        $this->assertTrue(Gate::forUser($soleAdmin)->allows('delete-and-block', $other));

        // A second administrator makes self-service deletion possible again.
        $this->userWithPermissions(['admin.users.assign-roles']);
        $this->assertTrue(Gate::forUser($soleAdmin)->allows('delete', $soleAdmin));
    }

    public function test_a_suspended_administrator_is_not_counted_for_the_safeguard(): void
    {
        $admin = $this->userWithPermissions(['admin.users.assign-roles', 'admin.users.delete']);
        $suspended = User::factory()->suspended()->create();
        $suspended->assignRole('admin');

        $this->assertFalse(app(PlatformAccess::class)->hasAnotherAdministrator($admin));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_lifecycle_abilities_require_their_permission(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();

        foreach (['suspend', 'block', 'unblock', 'delete', 'delete-and-block'] as $ability) {
            $this->assertFalse(
                Gate::forUser($actor)->allows($ability, $target),
                "Ability [{$ability}] must require its permission.",
            );
        }
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
