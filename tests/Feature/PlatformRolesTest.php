<?php

namespace Tests\Feature;

use App\Actions\Auth\ResolveGoogleApproval;
use App\Models\JobRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\JobMonitor;
use App\Support\PlatformAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlatformRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_system_roles_are_reconciled_and_delegates_cannot_assign_roles(): void
    {
        $catalog = Role::query()->where('slug', 'catalog-manager')->firstOrFail();
        $this->assertTrue($catalog->permissions()->where('route_name', 'admin.personal-products.promote')->exists());
        $this->assertFalse($catalog->permissions()->where('route_name', 'admin.users.assign-roles')->exists());
        $catalog->givePermissionTo([Permission::query()->where('route_name', 'admin.users.assign-roles')->firstOrFail()]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertFalse($catalog->permissions()->where('route_name', 'admin.users.assign-roles')->exists());
        $actor = User::factory()->create();
        $actor->assignRole($catalog);
        $target = User::factory()->create();
        $this->actingAs($actor)->patchJson(route('api.v1.admin.users.update', $target), ['roles' => ['admin']])->assertForbidden();
        $this->assertFalse($target->hasRole('admin'));
    }

    public function test_multiple_additional_roles_preserve_the_user_base(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $target = User::factory()->create();
        app(PlatformAccess::class)->assignRoles($actor, $target, ['catalog-manager', 'translation-manager']);
        $this->assertSame(['catalog-manager', 'translation-manager', 'user'], $target->getRoleNames()->sort()->values()->all());
        $this->assertTrue($target->getPermissionRouteNames()->contains('economy.me.store'));
        $this->assertFalse(app(PlatformAccess::class)->canManage($target));
    }

    public function test_last_approved_platform_administrator_cannot_be_demoted(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $pending = User::factory()->create(['approval_status' => 'pending']);
        $pending->assignRole('admin');
        $this->expectException(ValidationException::class);
        app(PlatformAccess::class)->assignRoles($actor, $actor, ['user']);
    }

    public function test_last_approved_administrator_is_protected_from_role_permission_removal(): void
    {
        $role = Role::create(['name' => 'Custom platform admin', 'slug' => 'custom-platform-admin']);
        $role->syncPermissions([Permission::query()->where('route_name', 'admin.users.assign-roles')->firstOrFail()]);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->expectException(ValidationException::class);
        $role->syncPermissions([]);
    }
}
