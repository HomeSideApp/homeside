<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);
        Permission::create(['name' => 'view users', 'route_name' => 'admin.users', 'description' => 'Ver usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'create user form', 'route_name' => 'admin.users.create', 'description' => 'Formulario crear usuario', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'store users', 'route_name' => 'admin.users.store', 'description' => 'Guardar usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'edit users', 'route_name' => 'admin.users.edit', 'description' => 'Formulario editar usuario', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'update users', 'route_name' => 'admin.users.update', 'description' => 'Actualizar usuarios', 'permission_group_id' => $adminGroup->id]);
        Permission::create(['name' => 'delete users', 'route_name' => 'admin.users.destroy', 'description' => 'Eliminar usuarios', 'permission_group_id' => $adminGroup->id]);

        $listasGroup = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'view list', 'route_name' => 'lists.show', 'description' => 'Ver una lista', 'permission_group_id' => $listasGroup->id]);
    }

    public function test_admin_can_access_users_page()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view users']);
        $admin->assignRole($role);

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk();
    }

    public function test_user_can_be_created_with_role()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view users', 'create user form', 'store users']);
        $admin->assignRole($role);

        Role::create(['name' => 'user', 'slug' => 'user']);

        Event::fake([Registered::class]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'role' => 'user',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users'));

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('user'));
        $this->assertNotNull($user->password);

        Event::assertDispatched(Registered::class, fn (Registered $event) => $event->user->id === $user->id);
    }

    public function test_user_role_can_be_changed()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view users', 'edit users', 'update users']);
        $admin->assignRole($role);

        $user = User::factory()->create();
        $userRole = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($userRole);

        Role::create(['name' => 'readonly', 'slug' => 'readonly']);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'readonly',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users'));

        $this->assertTrue($user->fresh()->hasRole('readonly'));
    }

    public function test_user_can_be_deleted()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view users', 'delete users']);
        $admin->assignRole($role);

        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_email_must_be_unique()
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['view users', 'create user form', 'store users']);
        $admin->assignRole($role);

        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'role' => 'user',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_without_manage_users_cannot_access()
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.users'));

        $response->assertForbidden();
    }
}
