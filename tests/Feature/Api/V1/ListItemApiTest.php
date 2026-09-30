<?php

namespace Tests\Feature\Api\V1;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\ListItem;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListItemApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected ShoppingList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'store list items', 'route_name' => 'households.lists.items.store', 'description' => 'Agregar items', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update list items', 'route_name' => 'households.lists.items.update', 'description' => 'Actualizar items', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete list items', 'route_name' => 'households.lists.items.destroy', 'description' => 'Eliminar items', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.show', 'description' => 'Ver imágenes de items', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['store list items', 'update list items', 'delete list items', 'view lists']);

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

        $this->list = ShoppingList::factory()->create([
            'created_by' => $this->user->id,
            'household_id' => $this->household->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_user_can_add_item_to_list(): void
    {
        $response = $this->postJson(route('api.v1.households.lists.items.store', [$this->household, $this->list]), [
            'custom_name' => 'Leche',
            'quantity' => 2,
            'unit' => 'l',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.custom_name', 'Leche');

        $this->assertTrue($response->json('data.quantity') == 2);

        $this->assertDatabaseHas('list_items', [
            'list_id' => $this->list->id,
            'custom_name' => 'Leche',
        ]);
    }

    public function test_repeated_requests_return_the_same_pending_product_without_creating_a_duplicate(): void
    {
        $product = Product::factory()->create();
        $route = route('api.v1.households.lists.items.store', [$this->household, $this->list]);
        $payload = ['product_id' => $product->id, 'quantity' => 1];

        $firstResponse = $this->postJson($route, $payload);
        $secondResponse = $this->postJson($route, $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertOk()
            ->assertJsonPath('data.id', $firstResponse->json('data.id'));
        $this->assertSame(1, $this->list->items()
            ->where('product_id', $product->id)
            ->where('is_checked', false)
            ->count());
    }

    public function test_household_member_can_add_item_to_list_created_by_another_member(): void
    {
        $foreignList = ShoppingList::factory()->create([
            'created_by' => User::factory(),
            'household_id' => $this->household->id,
        ]);

        $this->postJson(route('api.v1.households.lists.items.store', [$this->household, $foreignList]), [
            'custom_name' => 'Elemento compartido',
        ])->assertCreated();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $foreignList->id,
            'custom_name' => 'Elemento compartido',
        ]);
    }

    public function test_user_can_update_item(): void
    {
        $item = ListItem::factory()->create(['list_id' => $this->list->id]);

        $response = $this->putJson(route('api.v1.households.lists.items.update', [$this->household, $this->list, $item]), [
            'is_checked' => true,
            'quantity' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_checked', true);

        $this->assertTrue($response->json('data.quantity') == 5);
    }

    public function test_user_can_delete_item(): void
    {
        $item = ListItem::factory()->create(['list_id' => $this->list->id]);

        $response = $this->deleteJson(route('api.v1.households.lists.items.destroy', [$this->household, $this->list, $item]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_item_resource_uses_nested_api_image_url(): void
    {
        $item = ListItem::factory()->create([
            'list_id' => $this->list->id,
            'image_url' => 'list-items/photo.jpg',
        ]);

        $this->getJson(route('api.v1.households.lists.show', [$this->household, $this->list]))
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.image_url',
                route('api.v1.households.lists.items.image', [$this->household, $this->list, $item]),
            );
    }

    public function test_household_member_can_view_an_item_image_from_another_members_list(): void
    {
        Storage::fake('local');
        $imagePath = UploadedFile::fake()->image('item.jpg')->store('list-items', 'local');
        $otherUser = User::factory()->create();
        $otherUser->assignRole(Role::where('slug', 'user')->firstOrFail());
        HouseholdMember::create([
            'user_id' => $otherUser->id,
            'household_id' => $this->household->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $item = ListItem::factory()->create([
            'list_id' => $this->list->id,
            'image_url' => $imagePath,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson(route('api.v1.households.lists.items.image', [$this->household, $this->list, $item]))
            ->assertOk();
    }
}
