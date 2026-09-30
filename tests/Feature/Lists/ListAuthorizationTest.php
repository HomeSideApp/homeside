<?php

namespace Tests\Feature\Lists;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $listasGroup = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'view list', 'route_name' => 'households.lists.show', 'description' => 'Ver una lista', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'create lists', 'route_name' => 'households.lists.create', 'description' => 'Formulario crear listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'store lists', 'route_name' => 'households.lists.store', 'description' => 'Guardar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'edit lists', 'route_name' => 'households.lists.edit', 'description' => 'Formulario editar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'update lists', 'route_name' => 'households.lists.update', 'description' => 'Actualizar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'delete lists', 'route_name' => 'households.lists.destroy', 'description' => 'Eliminar listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'store list items', 'route_name' => 'households.lists.items.store', 'description' => 'Añadir elementos', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'update list items', 'route_name' => 'households.lists.items.update', 'description' => 'Actualizar elementos', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'delete list items', 'route_name' => 'households.lists.items.destroy', 'description' => 'Eliminar elementos', 'permission_group_id' => $listasGroup->id]);

        // Create 'user' role with basic list permissions
        $userRole = Role::create(['name' => 'user', 'slug' => 'user']);
        $userRole->syncPermissions([
            'view lists', 'view list', 'edit lists', 'update lists', 'delete lists',
            'store list items', 'update list items', 'delete list items',
        ]);
    }

    protected function createUserWithRole(string $roleName, array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => $roleName, 'slug' => Str::slug($roleName)]);
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }

    protected function createHouseholdForUser(User $user): Household
    {
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $household;
    }

    protected function addUserToHousehold(User $user, Household $household): void
    {
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    // ========== LISTS: HOUSEHOLD MEMBERS SHARE ACCESS ==========

    public function test_household_member_can_view_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('user', ['view lists', 'view list']);

        $household = $this->createHouseholdForUser($owner);
        $this->addUserToHousehold($user, $household);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->get(route('households.lists.show', [$household, $list]));

        $response->assertOk();
    }

    public function test_household_member_can_edit_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'edit lists', 'update lists']);

        $household = $this->createHouseholdForUser($owner);
        $this->addUserToHousehold($user, $household);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->get(route('households.lists.edit', [$household, $list]));

        $response->assertOk();
    }

    public function test_household_member_can_update_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'edit lists', 'update lists']);

        $household = $this->createHouseholdForUser($owner);
        $this->addUserToHousehold($user, $household);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->put(route('households.lists.update', [$household, $list]), [
            'name' => 'Compra actualizada',
        ]);

        $response->assertRedirect(route('households.lists.show', [$household, $list]));
        $this->assertDatabaseHas('shopping_lists', [
            'id' => $list->id,
            'name' => 'Compra actualizada',
        ]);
    }

    public function test_household_member_can_delete_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'delete lists']);

        $household = $this->createHouseholdForUser($owner);
        $this->addUserToHousehold($user, $household);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->delete(route('households.lists.destroy', [$household, $list]));

        $response->assertRedirect(route('households.lists.index', $household));
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    // ========== LISTS: MEMBERSHIP IS REQUIRED ==========

    public function test_non_member_cannot_access_household_list_even_with_route_permissions(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('outsider', ['view lists', 'view list', 'edit lists', 'update lists', 'delete lists']);

        $household = $this->createHouseholdForUser($owner);
        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);

        $this->assertFalse($user->can('view', $list));
        $this->assertFalse($user->can('update', $list));
        $this->assertFalse($user->can('delete', $list));
        $this->assertFalse($user->can('manage', $list));
    }

    public function test_disabling_households_keeps_personal_access_and_removes_household_access(): void
    {
        $user = $this->createUserWithRole('personal-only', ['view list']);
        $household = $this->createHouseholdForUser($user);
        $householdList = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => $household->id,
        ]);
        $personalList = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => null,
        ]);
        $user->update([
            'households_enabled' => false,
            'active_household_id' => null,
        ]);

        $this->assertFalse($user->can('view', $householdList));
        $this->assertTrue($user->can('view', $personalList));
    }

    // ========== ITEMS: CANNOT ACCESS ITEMS FROM OTHER LISTS ==========

    public function test_item_from_other_list_returns_404(): void
    {
        $user = $this->createUserWithRole('user', [
            'view lists', 'view list',
            'store list items', 'update list items', 'delete list items',
        ]);

        $household = $this->createHouseholdForUser($user);

        $listA = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $listB = ShoppingList::factory()->create(['household_id' => $household->id]);

        $itemInB = ListItem::factory()->create(['list_id' => $listB->id]);

        $response = $this->actingAs($user)->put(route('households.lists.items.update', [$household, $listA, $itemInB]), [
            'is_checked' => true,
        ]);
        $response->assertNotFound();

        $response = $this->actingAs($user)->delete(route('households.lists.items.destroy', [$household, $listA, $itemInB]));
        $response->assertNotFound();
    }

    public function test_user_cannot_modify_another_users_personal_list_item(): void
    {
        $owner = User::factory()->create();
        $user = $this->createUserWithRole('user', [
            'view lists', 'view list',
            'store list items', 'update list items', 'delete list items',
        ]);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => null]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $response = $this->actingAs($user)->put(route('lists.items.update', [$list, $item]), [
            'is_checked' => true,
        ]);
        $response->assertForbidden();

        $response = $this->actingAs($user)->delete(route('lists.items.destroy', [$list, $item]));
        $response->assertForbidden();
    }
}
