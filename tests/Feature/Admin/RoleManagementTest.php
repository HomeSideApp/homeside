<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);
        Permission::create(['name' => 'view roles', 'route_name' => 'admin.roles', 'description' => 'Ver roles', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'create role form', 'route_name' => 'admin.roles.create', 'description' => 'Formulario crear rol', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'store roles', 'route_name' => 'admin.roles.store', 'description' => 'Guardar roles', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'show role', 'route_name' => 'admin.roles.show', 'description' => 'Ver rol', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'edit roles', 'route_name' => 'admin.roles.edit', 'description' => 'Formulario editar rol', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'update roles', 'route_name' => 'admin.roles.update', 'description' => 'Actualizar roles', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'delete roles', 'route_name' => 'admin.roles.destroy', 'description' => 'Eliminar roles', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'view users', 'route_name' => 'admin.users', 'description' => 'Ver usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'test permission', 'route_name' => 'test.permission', 'description' => 'Permiso de prueba', 'permission_group_id' => $adminGroup->id]);
    }

    public function test_admin_can_access_roles_page()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'view users']);
        $admin->assignRole($role);

        $response = $this->actingAs($admin)->get(route('admin.roles'));

        $response->assertOk();
    }

    public function test_user_without_manage_roles_cannot_access_roles_page()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.roles'));

        $response->assertForbidden();
    }

    public function test_role_can_be_created()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'create role form', 'store roles']);
        $admin->assignRole($role);

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'editor',
            'permissions' => ['test permission'],
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.roles'));

        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    }

    public function test_role_name_must_be_unique()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'create role form', 'store roles']);
        $admin->assignRole($role);

        Role::create(['name' => 'existing', 'slug' => 'existing']);

        $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'existing',
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_role_permissions_can_be_updated()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'edit roles', 'update roles']);
        $admin->assignRole($role);

        $editorRole = Role::create(['name' => 'editor', 'slug' => 'editor']);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $editorRole), [
            'name' => 'editor',
            'permissions' => ['test permission'],
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.roles'));

        $this->assertTrue($editorRole->fresh()->hasPermissionTo('test permission'));
    }

    public function test_role_can_be_deleted()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'delete roles']);
        $admin->assignRole($role);

        $toDelete = Role::create(['name' => 'to-delete', 'slug' => 'to-delete']);

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $toDelete));

        $response->assertRedirect(route('admin.roles'));
        $this->assertDatabaseMissing('roles', ['id' => $toDelete->id]);
    }

    public function test_system_role_cannot_be_edited()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'edit roles', 'update roles']);
        $admin->assignRole($role);

        $systemRole = Role::create(['name' => 'System Role', 'slug' => 'system-role', 'is_system' => true]);

        $response = $this->actingAs($admin)->put(route('admin.roles.update', $systemRole), [
            'name' => 'Modified System Role',
            'permissions' => ['test permission'],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id, 'name' => 'System Role']);
    }

    public function test_system_role_cannot_be_deleted()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'delete roles']);
        $admin->assignRole($role);

        $systemRole = Role::create(['name' => 'System Role', 'slug' => 'system-role', 'is_system' => true]);

        $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $systemRole));

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_system_role_can_be_viewed()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'show role']);
        $admin->assignRole($role);

        $systemRole = Role::create(['name' => 'System Role', 'slug' => 'system-role', 'is_system' => true]);
        $systemRole->givePermissionTo(['test permission']);

        $response = $this->actingAs($admin)->get(route('admin.roles.show', $systemRole));

        $response->assertOk();
    }

    public function test_regular_role_can_be_viewed()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view roles', 'show role']);
        $admin->assignRole($role);

        $regularRole = Role::create(['name' => 'Regular Role', 'slug' => 'regular-role']);
        $regularRole->givePermissionTo(['test permission']);

        $response = $this->actingAs($admin)->get(route('admin.roles.show', $regularRole));

        $response->assertOk();
    }
}
