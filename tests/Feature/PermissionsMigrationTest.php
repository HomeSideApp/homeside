<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermissionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_and_repeated_conversion_preserve_custom_roles_and_access(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $owner->id, 'role' => 'admin']);
        $user = User::factory()->create();
        foreach (['readonly', 'lists-editor', 'user-invitation'] as $slug) {
            $role = Role::create(['slug' => $slug, 'name' => $slug, 'is_system' => true]);
            if ($slug !== 'user-invitation') {
                $user->assignRole($role);
            }
        }
        $custom = Role::create(['slug' => 'custom', 'name' => 'Custom']);
        $user->assignRole($custom);
        $member = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id]);
        HouseholdMember::query()->whereKey($member->id)->update(['access_profile' => null, 'module_access' => null]);
        $invited = User::factory()->create();
        $invited->assignRole('user-invitation');

        $this->artisan('households:migrate-permissions', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($member->refresh()->access_profile);
        $this->assertFalse(Role::query()->where('slug', 'user')->exists());
        $this->artisan('households:migrate-permissions')->assertSuccessful();
        $this->assertSame(['shopping_lists' => 'write', 'recipes' => 'read', 'economy' => 'read', 'contacts' => 'read'], $member->refresh()->permissions());
        $this->assertTrue($user->hasRole('user', 'custom'));
        $this->assertDatabaseMissing('roles', ['slug' => 'readonly']);
        $this->assertFalse($invited->refresh()->can_create_households);
        $member->update(['module_access' => array_replace($member->permissions(), ['economy' => 'none'])]);
        $this->artisan('households:migrate-permissions')->assertSuccessful();
        $this->assertSame('none', $member->refresh()->permissions()['economy']);
        $this->assertFalse($invited->refresh()->can_create_households);
        $this->assertTrue($user->hasRole('custom'));
    }

    public function test_conversion_requires_the_schema_migration_first(): void
    {
        Schema::partialMock()->shouldReceive('hasColumns')->once()->andReturnFalse();
        $this->artisan('households:migrate-permissions')->assertFailed();
        $this->assertDatabaseMissing('roles', ['slug' => 'user']);
    }

    public function test_invalid_invitation_access_prevents_partial_conversion(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $owner->id, 'role' => 'admin']);
        $invitation = HouseholdInvitation::create(['household_id' => $household->id, 'invited_by' => $owner->id, 'email' => 'invite@example.test', 'status' => 'pending', 'token' => (string) Str::uuid(), 'expires_at' => now()->addDay(), 'module_access' => ['economy' => 'unexpected']]);
        $this->artisan('households:migrate-permissions')->assertFailed();
        $this->assertSame(['economy' => 'unexpected'], $invitation->refresh()->module_access);
        $this->assertDatabaseMissing('roles', ['slug' => 'user']);
    }

    public function test_inconsistent_ownership_aborts_before_any_assignments_change(): void
    {
        $user = User::factory()->create();
        Role::create(['slug' => 'readonly', 'name' => 'Readonly']);
        $user->assignRole('readonly');
        Household::factory()->create(['created_by' => $user->id]);
        $this->artisan('households:migrate-permissions')->assertFailed();
        $this->assertTrue($user->hasRole('readonly'));
        $this->assertDatabaseMissing('roles', ['slug' => 'user']);
    }

    public function test_custom_legacy_permissions_are_derived_without_granting_other_modules(): void
    {
        $owner = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $owner->id, 'role' => 'admin']);
        $user = User::factory()->create();
        $group = PermissionGroup::create(['name' => 'Custom']);
        $permission = Permission::create(['name' => 'custom lists read', 'description' => 'Custom', 'route_name' => 'households.lists.show', 'permission_group_id' => $group->id]);
        $role = Role::create(['slug' => 'custom', 'name' => 'Custom']);
        $role->syncPermissions([$permission]);
        $user->assignRole($role);
        $member = HouseholdMember::factory()->create(['household_id' => $household->id, 'user_id' => $user->id]);
        HouseholdMember::query()->whereKey($member->id)->update(['access_profile' => null, 'module_access' => null]);
        $this->artisan('households:migrate-permissions')->assertSuccessful();
        $this->assertSame(['shopping_lists' => 'read', 'recipes' => 'none', 'economy' => 'none', 'contacts' => 'none'], $member->refresh()->permissions());
        $this->assertTrue($user->hasRole('custom'));
    }
}
