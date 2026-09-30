<?php

namespace Tests\Feature\Lists;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_household_list_index_renders_the_unified_filtered_view(): void
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list']);
        $household = $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->get(route('households.lists.index', $household));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Index')
            ->where('filters.scope', 'household')
            ->where('filters.household', $household->id)
        );
    }

    public function test_show_page_loads_icons_only_through_a_partial_reload(): void
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => $household->id,
        ]);

        $response = $this->actingAs($user)->get(route('households.lists.show', [$household, $list]));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Show')
            ->missing('icons')
            ->reloadOnly('icons', fn (Assert $reload): Assert => $reload
                ->has('icons')
                ->where('icons.0.type', 'static')));
    }

    public function test_user_can_create_list()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'create lists', 'store lists']);
        $household = $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->post(route('households.lists.store', $household), [
            'name' => 'Weekly Shopping',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('shopping_lists', [
            'name' => 'Weekly Shopping',
            'created_by' => $user->id,
            'household_id' => $household->id,
        ]);
    }

    public function test_list_name_is_required()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'create lists', 'store lists']);
        $household = $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->post(route('households.lists.store', $household), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_user_can_edit_own_list()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'edit lists', 'update lists']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->put(route('households.lists.update', [$household, $list]), [
            'name' => 'Updated Name',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('shopping_lists', [
            'id' => $list->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_delete_own_list()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'delete lists']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->delete(route('households.lists.destroy', [$household, $list]));

        $response->assertRedirect(route('households.lists.index', $household));
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    public function test_readonly_user_cannot_create_list()
    {
        $user = $this->createUserWithRole('readonly', ['view lists', 'view list']);
        $household = $this->createHouseholdForUser($user);

        $response = $this->actingAs($user)->get(route('households.lists.create', $household));

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_can_create_a_private_list_without_a_household(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view lists', 'view list', 'create lists', 'store lists']);

        $response = $this->actingAs($user)->post(route('lists.store'), [
            'name' => 'Compra privada',
        ]);

        $list = ShoppingList::query()->where('name', 'Compra privada')->firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('lists.show', $list));
        $this->assertSame($user->id, $list->created_by);
        $this->assertNull($list->household_id);
    }

    public function test_list_index_includes_personal_and_accessible_household_lists(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view lists']);
        $household = $this->createHouseholdForUser($user);
        $privateList = ShoppingList::factory()->create([
            'name' => 'Privada',
            'created_by' => $user->id,
            'household_id' => null,
            'created_at' => now()->subMinute(),
        ]);
        $householdList = ShoppingList::factory()->create([
            'name' => 'Compartida',
            'created_by' => User::factory()->create()->id,
            'household_id' => $household->id,
            'created_at' => now(),
        ]);
        ShoppingList::factory()->create([
            'name' => 'Personal ajena',
            'created_by' => User::factory(),
            'household_id' => null,
        ]);
        $foreignHousehold = Household::factory()->create();
        ShoppingList::factory()->create([
            'name' => 'Otro hogar',
            'created_by' => User::factory(),
            'household_id' => $foreignHousehold->id,
        ]);

        $response = $this->actingAs($user)->get(route('lists.index'));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Index')
            ->has('lists', 2)
            ->where('lists.0.id', $householdList->id)
            ->where('lists.0.household.id', $household->id)
            ->where('lists.1.id', $privateList->id)
            ->where('lists.1.household', null)
            ->has('households', 1)
            ->where('activeHouseholdId', $household->id)
            ->where('filters.scope', 'all')
        );
    }

    public function test_list_index_filters_by_scope_household_and_search(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view lists']);
        $household = $this->createHouseholdForUser($user);
        ShoppingList::factory()->create([
            'name' => 'Farmacia personal',
            'created_by' => $user->id,
            'household_id' => null,
        ]);
        $matchingList = ShoppingList::factory()->create([
            'name' => 'Compra semanal',
            'created_by' => User::factory(),
            'household_id' => $household->id,
        ]);
        ShoppingList::factory()->create([
            'name' => 'Farmacia del hogar',
            'created_by' => User::factory(),
            'household_id' => $household->id,
        ]);

        $response = $this->actingAs($user)->get(route('lists.index', [
            'search' => 'semanal',
            'scope' => 'household',
            'household' => $household->id,
        ]));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Index')
            ->has('lists', 1)
            ->where('lists.0.id', $matchingList->id)
            ->where('filters.search', 'semanal')
            ->where('filters.scope', 'household')
            ->where('filters.household', $household->id)
        );
    }

    public function test_list_index_does_not_leak_a_foreign_household_through_filters(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view lists']);
        $foreignHousehold = Household::factory()->create();
        ShoppingList::factory()->create([
            'name' => 'Compra secreta',
            'created_by' => User::factory(),
            'household_id' => $foreignHousehold->id,
        ]);

        $response = $this->actingAs($user)->get(route('lists.index', [
            'scope' => 'household',
            'household' => $foreignHousehold->id,
        ]));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Index')
            ->has('lists', 0)
            ->has('households', 0)
        );
    }

    public function test_list_index_hides_household_lists_when_households_are_disabled(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view lists']);
        $household = $this->createHouseholdForUser($user);
        $privateList = ShoppingList::factory()->create([
            'name' => 'Solo mía',
            'created_by' => $user->id,
            'household_id' => null,
        ]);
        ShoppingList::factory()->create([
            'name' => 'Compartida',
            'created_by' => User::factory(),
            'household_id' => $household->id,
        ]);
        $user->update([
            'households_enabled' => false,
            'active_household_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('lists.index'));

        $response->assertInertia(fn (Assert $page): Assert => $page
            ->component('lists/Index')
            ->has('lists', 1)
            ->where('lists.0.id', $privateList->id)
            ->has('households', 0)
            ->where('activeHouseholdId', null)
        );
    }

    public function test_household_list_is_not_accessible_through_private_routes(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view list']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => $household->id,
        ]);

        $response = $this->actingAs($user)->get(route('lists.show', $list));

        $response->assertNotFound();
    }

    public function test_user_can_add_an_item_to_a_private_list(): void
    {
        $user = $this->createUserWithRole('personal-user', ['view list', 'store list items']);
        $list = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => null,
        ]);

        $response = $this->actingAs($user)->post(route('lists.items.store', $list), [
            'custom_name' => 'Leche',
            'quantity' => 1,
        ]);

        $response->assertRedirect(route('lists.show', $list));
        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'custom_name' => 'Leche',
            'added_by' => $user->id,
        ]);
    }

    public function test_household_list_cannot_be_modified_through_private_item_routes(): void
    {
        $user = $this->createUserWithRole('personal-user', ['store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create([
            'created_by' => $user->id,
            'household_id' => $household->id,
        ]);

        $response = $this->actingAs($user)->post(route('lists.items.store', $list), [
            'custom_name' => 'No permitido',
            'quantity' => 1,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('list_items', [
            'list_id' => $list->id,
            'custom_name' => 'No permitido',
        ]);
    }
}
