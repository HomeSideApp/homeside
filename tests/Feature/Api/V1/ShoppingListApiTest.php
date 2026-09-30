<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShoppingListApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view list', 'route_name' => 'households.lists.show', 'description' => 'Ver una lista', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store lists', 'route_name' => 'households.lists.store', 'description' => 'Crear listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update lists', 'route_name' => 'households.lists.update', 'description' => 'Actualizar listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete lists', 'route_name' => 'households.lists.destroy', 'description' => 'Eliminar listas', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store list items', 'route_name' => 'households.lists.items.store', 'description' => 'Añadir elementos', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists', 'view list', 'store lists', 'update lists', 'delete lists', 'store list items']);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        $this->user->update(['active_household_id' => $this->household->id]);

        Sanctum::actingAs($this->user);
    }

    // ========== INDEX ==========

    public function test_user_can_list_all_lists_in_their_household(): void
    {
        ShoppingList::factory()->count(2)->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
        ShoppingList::factory()->create([
            'created_by' => User::factory(),
            'household_id' => $this->household->id,
        ]);

        $response = $this->getJson(route('api.v1.households.lists.index', $this->household));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'created_by', 'household_id', 'scope', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonPath('data.0.scope', 'household');
    }

    public function test_unauthenticated_user_cannot_list_lists(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson(route('api.v1.households.lists.index', $this->household));

        $response->assertUnauthorized();
    }

    public function test_non_member_cannot_list_or_create_lists_for_household(): void
    {
        $foreignHousehold = Household::factory()->create();

        $this->getJson(route('api.v1.households.lists.index', $foreignHousehold))
            ->assertForbidden();

        $this->postJson(route('api.v1.households.lists.store', $foreignHousehold), [
            'name' => 'Lista no autorizada',
        ])->assertForbidden();

        $this->assertDatabaseMissing('shopping_lists', [
            'household_id' => $foreignHousehold->id,
            'name' => 'Lista no autorizada',
        ]);
    }

    public function test_user_cannot_access_household_lists_when_households_are_disabled(): void
    {
        $this->user->update([
            'households_enabled' => false,
            'active_household_id' => null,
        ]);

        $this->getJson(route('api.v1.households.lists.index', $this->household))
            ->assertForbidden();

        $this->postJson(route('api.v1.households.lists.store', $this->household), [
            'name' => 'No permitida',
        ])->assertForbidden();

        $this->assertDatabaseMissing('shopping_lists', [
            'household_id' => $this->household->id,
            'name' => 'No permitida',
        ]);
    }

    public function test_list_response_does_not_expose_sensitive_fields(): void
    {
        ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $response = $this->getJson(route('api.v1.households.lists.index', $this->household));

        $response->assertOk()
            ->assertJsonMissing(['password', 'remember_token', 'two_factor_secret']);
    }

    public function test_dates_are_formatted_as_iso_8601(): void
    {
        ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $response = $this->getJson(route('api.v1.households.lists.index', $this->household));

        $response->assertOk();
        $date = $response->json('data.0.created_at');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $date);
    }

    // ========== SHOW ==========

    public function test_user_can_view_list_with_relations(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $response = $this->getJson(route('api.v1.households.lists.show', [$this->household, $list]));

        $response->assertOk()
            ->assertJsonPath('data.id', $list->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'created_by', 'created_at', 'updated_at',
                    'items',
                ],
            ]);
    }

    public function test_nonexistent_list_returns_404(): void
    {
        $response = $this->getJson(route('api.v1.households.lists.show', [$this->household, 'non-existent-id']));

        $response->assertNotFound();
    }

    // ========== STORE ==========

    public function test_user_can_create_a_list(): void
    {
        $response = $this->postJson(route('api.v1.households.lists.store', $this->household), [
            'name' => 'Lista de la compra',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Lista de la compra')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'created_by', 'created_at'],
            ]);

        $this->assertDatabaseHas('shopping_lists', [
            'name' => 'Lista de la compra',
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);
    }

    public function test_validation_error_returns_422(): void
    {
        $response = $this->postJson(route('api.v1.households.lists.store', $this->household), [
            'name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name')
            ->assertJsonStructure([
                'message',
                'errors' => ['name'],
            ]);
    }

    // ========== UPDATE ==========

    public function test_user_can_update_their_list(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $response = $this->putJson(route('api.v1.households.lists.update', [$this->household, $list]), [
            'name' => 'Nombre actualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nombre actualizado');

        $this->assertDatabaseHas('shopping_lists', [
            'id' => $list->id,
            'name' => 'Nombre actualizado',
        ]);
    }

    // ========== DELETE ==========

    public function test_user_can_delete_their_list(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $response = $this->deleteJson(route('api.v1.households.lists.destroy', [$this->household, $list]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    public function test_error_response_has_consistent_structure(): void
    {
        $response = $this->postJson(route('api.v1.households.lists.store', $this->household), []);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_user_can_create_and_list_private_lists(): void
    {
        ShoppingList::factory()->create([
            'name' => 'Lista del hogar',
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $createResponse = $this->postJson(route('api.v1.lists.store'), [
            'name' => 'Lista privada',
        ]);

        $privateListId = $createResponse->json('data.id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lista privada');
        $this->getJson(route('api.v1.lists.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $privateListId);
        $this->assertDatabaseHas('shopping_lists', [
            'id' => $privateListId,
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);
    }

    public function test_household_list_is_not_accessible_through_private_api_routes(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        $this->getJson(route('api.v1.lists.show', $list))->assertNotFound();
    }

    public function test_user_can_add_an_item_to_a_private_list_through_the_api(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => null,
        ]);

        $this->postJson(route('api.v1.lists.items.store', $list), [
            'custom_name' => 'Pan',
            'quantity' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'custom_name' => 'Pan',
            'added_by' => $this->user->id,
        ]);
    }

    // ========== IDOR PROTECTION ==========

    public function test_household_member_can_show_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $this->household->id]);

        $response = $this->getJson(route('api.v1.households.lists.show', [$this->household, $list]));

        $response->assertOk()
            ->assertJsonPath('data.id', $list->id)
            ->assertJsonPath('data.scope', 'household');
    }

    public function test_household_member_can_update_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $this->household->id]);

        $response = $this->putJson(route('api.v1.households.lists.update', [$this->household, $list]), [
            'name' => 'Compra actualizada',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Compra actualizada');
        $this->assertDatabaseHas('shopping_lists', [
            'id' => $list->id,
            'name' => 'Compra actualizada',
        ]);
    }

    public function test_household_member_can_delete_list_created_by_another_member(): void
    {
        $owner = User::factory()->create();

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $this->household->id]);

        $response = $this->deleteJson(route('api.v1.households.lists.destroy', [$this->household, $list]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
    }

    public function test_user_cannot_show_another_users_personal_list(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => User::factory(),
            'household_id' => null,
        ]);

        $response = $this->getJson(route('api.v1.lists.show', $list));

        $response->assertForbidden();
    }

    public function test_user_cannot_update_another_users_personal_list(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => User::factory(),
            'household_id' => null,
        ]);

        $response = $this->putJson(route('api.v1.lists.update', $list), [
            'name' => 'No permitido',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('shopping_lists', [
            'id' => $list->id,
            'name' => 'No permitido',
        ]);
    }

    public function test_user_cannot_delete_another_users_personal_list(): void
    {
        $list = ShoppingList::factory()->create([
            'created_by' => User::factory(),
            'household_id' => null,
        ]);

        $response = $this->deleteJson(route('api.v1.lists.destroy', $list));

        $response->assertForbidden();
        $this->assertModelExists($list);
    }
}
