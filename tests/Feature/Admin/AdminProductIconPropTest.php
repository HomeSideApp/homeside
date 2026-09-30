<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminProductIconPropTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Admin']);
        $createPermission = Permission::create([
            'name' => 'create product form',
            'route_name' => 'admin.products.create',
            'description' => 'Formulario crear producto',
            'permission_group_id' => $group->id,
        ]);
        $editPermission = Permission::create([
            'name' => 'edit product form',
            'route_name' => 'admin.products.edit',
            'description' => 'Formulario editar producto',
            'permission_group_id' => $group->id,
        ]);
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions([$createPermission, $editPermission]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_create_page_loads_icons_only_through_a_partial_reload(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.products.create'));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/Products/Create')
            ->missing('icons')
            ->reloadOnly('icons', fn (Assert $reload): Assert => $reload
                ->has('icons')
                ->where('icons.0.type', 'static')));
    }

    public function test_edit_page_loads_icons_only_through_a_partial_reload(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('admin.products.edit', $product));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('admin/Products/Edit')
            ->missing('icons')
            ->reloadOnly('icons', fn (Assert $reload): Assert => $reload
                ->has('icons')
                ->where('icons.0.type', 'static')));
    }
}
