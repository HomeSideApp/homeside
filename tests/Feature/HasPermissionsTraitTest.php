<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasPermissionsTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_checks_roles_by_slug(): void
    {
        $user = User::factory()->create();
        $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $user->assignRole($editor);

        $this->assertTrue($user->hasRole('admin', 'editor'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_it_returns_unique_permission_route_names_from_all_roles(): void
    {
        $user = User::factory()->create();
        $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $reviewer = Role::create(['name' => 'Reviewer', 'slug' => 'reviewer']);
        $permissionGroup = PermissionGroup::create(['name' => 'Recipes']);

        $viewPermission = Permission::create([
            'name' => 'View recipes',
            'route_name' => 'recipes.index',
            'description' => 'View recipes',
            'permission_group_id' => $permissionGroup->id,
        ]);
        $editPermission = Permission::create([
            'name' => 'Edit recipes',
            'route_name' => 'recipes.edit',
            'description' => 'Edit recipes',
            'permission_group_id' => $permissionGroup->id,
        ]);

        $editor->permissions()->attach([$viewPermission->id, $editPermission->id]);
        $reviewer->permissions()->attach($viewPermission);
        $user->roles()->attach([$editor->id, $reviewer->id]);

        $this->assertSame(
            ['recipes.index', 'recipes.edit'],
            $user->getPermissionRouteNames()->all(),
        );
        $this->assertSame(['editor', 'reviewer'], $user->getRoleNames()->all());
    }

    public function test_it_returns_empty_collections_without_roles(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $user->getPermissionRouteNames()->all());
        $this->assertSame([], $user->getRoleNames()->all());
    }
}
