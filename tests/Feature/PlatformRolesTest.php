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

    public function test_api_supports_legacy_and_multiple_roles_and_protects_the_last_administrator(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $target = User::factory()->create();
        $this->actingAs($actor)->patchJson(route('api.v1.admin.users.update', $target), ['role' => 'catalog-manager'])->assertOk();
        $this->assertSame(['catalog-manager', 'user'], $target->getRoleNames()->sort()->values()->all());
        $this->patchJson(route('api.v1.admin.users.update', $target), ['roles' => ['catalog-manager', 'translation-manager']])->assertOk();
        $this->assertSame(['catalog-manager', 'translation-manager', 'user'], $target->getRoleNames()->sort()->values()->all());
        $this->patchJson(route('api.v1.admin.users.update', $actor), ['name' => 'Changed', 'roles' => ['user']])->assertUnprocessable();
        $this->assertNotSame('Changed', $actor->refresh()->name);
        $this->assertTrue($actor->hasRole('admin'));
    }

    public function test_user_data_editing_permission_does_not_allow_granting_roles(): void
    {
        $role = Role::create(['name' => 'Data editor', 'slug' => 'data-editor']);
        $role->syncPermissions([Permission::query()->where('route_name', 'admin.users.update')->firstOrFail()]);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $target = User::factory()->create();
        $this->actingAs($actor)->patchJson(route('api.v1.admin.users.update', $target), ['name' => 'Allowed'])->assertOk();
        $this->patchJson(route('api.v1.admin.users.update', $target), ['name' => 'Blocked', 'roles' => ['admin']])->assertForbidden();
        $this->assertSame('Allowed', $target->refresh()->name);
        $this->assertFalse($target->hasRole('admin'));
        $this->patchJson(route('api.v1.admin.users.update', $target), ['can_create_households' => false])->assertForbidden();
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

    public function test_stale_pending_account_cannot_be_rejected_after_approval(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $this->actingAs($actor);
        $target = User::factory()->create(['approval_status' => 'pending']);
        $target->googleIdentity()->create(['google_sub' => 'stale-pending']);
        $target->assignRole('admin');
        User::query()->whereKey($target->id)->update(['approval_status' => 'approved']);
        try {
            app(ResolveGoogleApproval::class)->reject($target);
            $this->fail('A rejection must recheck approval under the lock.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertTrue($target->refresh()->hasRole('admin'));
        $this->assertSame('approved', $target->approval_status);
    }

    public function test_console_account_creation_also_assigns_the_user_base(): void
    {
        Notification::fake();
        $this->artisan('app:create-user')
            ->expectsQuestion(__('console.create_user.name'), 'Catalog operator')
            ->expectsQuestion(__('console.create_user.email'), 'catalog@example.test')
            ->expectsQuestion(__('console.create_user.role'), 'catalog-manager')
            ->expectsConfirmation(__('console.create_user.confirm'), 'yes')
            ->assertSuccessful();
        $user = User::query()->where('email', 'catalog@example.test')->firstOrFail();
        $this->assertSame(['catalog-manager', 'user'], $user->getRoleNames()->sort()->values()->all());
    }
}
