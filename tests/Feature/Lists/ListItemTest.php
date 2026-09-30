<?php

namespace Tests\Feature\Lists;

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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $listasGroup = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $listasGroup->id]);
        Permission::create(['name' => 'view list', 'route_name' => 'households.lists.show', 'description' => 'Ver una lista', 'permission_group_id' => $listasGroup->id]);
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

    protected function addUserToHousehold(User $user, Household $household): void
    {
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    public function test_user_can_add_item_to_list()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $response = $this->actingAs($user)->post(route('households.lists.items.store', [$household, $list]), [
            'custom_name' => 'Milk',
            'quantity' => 2,
            'unit' => 'l',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'custom_name' => 'Milk',
        ]);
    }

    public function test_repeated_requests_do_not_add_the_same_pending_product_twice(): void
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $product = Product::factory()->create();
        $route = route('households.lists.items.store', [$household, $list]);

        $this->actingAs($user)->post($route, ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();
        $this->actingAs($user)->post($route, ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();

        $this->assertSame(1, $list->items()
            ->where('product_id', $product->id)
            ->where('is_checked', false)
            ->count());
    }

    public function test_user_can_check_item()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'update list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id, 'is_checked' => false]);

        $response = $this->actingAs($user)->put(route('households.lists.items.update', [$household, $list, $item]), [
            'is_checked' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue($item->fresh()->is_checked);
    }

    public function test_user_can_delete_item()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'delete list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $response = $this->actingAs($user)->delete(route('households.lists.items.destroy', [$household, $list, $item]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('list_items', ['id' => $item->id]);
    }

    public function test_user_can_upload_image_to_list_item()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'update list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);

        $response = $this->actingAs($user)->put(route('households.lists.items.update', [$household, $list, $item]), [
            'image_url' => $image,
        ], ['Content-Type' => 'multipart/form-data']);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull($item->fresh()->image_url);
        $this->assertStringContainsString('list-items/', $item->fresh()->image_url);
    }

    public function test_user_can_view_list_item_image()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'update list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);
        $path = $image->storeAs('list-items/'.$list->id, 'test-image.jpg', 'local');
        $item->update(['image_url' => $path]);

        $response = $this->actingAs($user)->get(route('images.show', ['type' => 'list_item', 'uuid' => $item->id]));

        $response->assertOk();
    }

    public function test_user_without_access_cannot_view_image()
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $otherUser = $this->createUserWithRole('user', ['view lists', 'view list']);

        $household = $this->createHouseholdForUser($owner);
        $this->addUserToHousehold($otherUser, $household);

        $list = ShoppingList::factory()->create(['created_by' => $owner->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);
        $path = $image->storeAs('list-items/'.$list->id, 'test-image.jpg', 'local');
        $item->update(['image_url' => $path]);

        $response = $this->actingAs($otherUser)->get(route('images.show', ['type' => 'list_item', 'uuid' => $item->id]));

        $response->assertForbidden();
    }

    public function test_new_item_inherits_image_from_previous_item_of_same_product()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        // Store an image manually for the previous (checked) item
        $image = UploadedFile::fake()->image('product.jpg', 200, 200);
        $path = $image->storeAs('list-items/'.$list->id, 'test-image.jpg', 'local');

        $previousItem = ListItem::factory()->create([
            'list_id' => $list->id,
            'image_url' => $path,
            'is_checked' => true,
        ]);

        $response = $this->actingAs($user)->post(route('households.lists.items.store', [$household, $list]), [
            'product_id' => $previousItem->product_id,
            'quantity' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $newItem = ListItem::where('list_id', $list->id)
            ->where('product_id', $previousItem->product_id)
            ->where('id', '!=', $previousItem->id)
            ->first();

        $this->assertNotNull($newItem);
        $this->assertFalse($newItem->is_checked);
        $this->assertNotNull($newItem->image_url);
        $this->assertNotEquals($path, $newItem->image_url);
        Storage::disk('local')->assertExists($newItem->image_url);
        Storage::disk('local')->assertExists($path);
    }

    public function test_new_item_without_previous_image_has_no_image()
    {
        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $item = ListItem::factory()->create(['list_id' => $list->id, 'image_url' => null]);

        $response = $this->actingAs($user)->post(route('households.lists.items.store', [$household, $list]), [
            'product_id' => $item->product_id,
            'quantity' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'product_id' => $item->product_id,
            'image_url' => null,
        ]);
    }

    public function test_new_item_does_not_inherit_image_from_other_product()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);
        $path = $image->storeAs('list-items/'.$list->id, 'test-image.jpg', 'local');

        $previousItem = ListItem::factory()->create([
            'list_id' => $list->id,
            'image_url' => $path,
        ]);

        $response = $this->actingAs($user)->post(route('households.lists.items.store', [$household, $list]), [
            'custom_name' => 'Other product',
            'quantity' => 1,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('list_items', [
            'list_id' => $list->id,
            'custom_name' => 'Other product',
            'image_url' => null,
        ]);
    }

    public function test_replacing_image_on_inherited_item_does_not_break_previous_item()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'store list items', 'update list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);

        $image = UploadedFile::fake()->image('product.jpg', 200, 200);
        $path = $image->storeAs('list-items/'.$list->id, 'test-image.jpg', 'local');

        $previousItem = ListItem::factory()->create([
            'list_id' => $list->id,
            'image_url' => $path,
            'is_checked' => true,
        ]);

        // Re-add the product: inherits a copy of the image
        $this->actingAs($user)->post(route('households.lists.items.store', [$household, $list]), [
            'product_id' => $previousItem->product_id,
            'quantity' => 1,
        ]);

        $newItem = ListItem::where('list_id', $list->id)
            ->where('product_id', $previousItem->product_id)
            ->where('id', '!=', $previousItem->id)
            ->first();

        // Replace the image on the new item
        $newImage = UploadedFile::fake()->image('new.jpg', 200, 200);
        $response = $this->actingAs($user)->put(route('households.lists.items.update', [$household, $list, $newItem]), [
            'image_url' => $newImage,
        ], ['Content-Type' => 'multipart/form-data']);

        $response->assertSessionHasNoErrors();

        // The previous item's image file must remain intact
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $previousItem->fresh()->image_url);
    }

    public function test_old_image_is_deleted_when_new_one_is_uploaded()
    {
        Storage::fake('local');

        $user = $this->createUserWithRole('user', ['view lists', 'view list', 'update list items']);
        $household = $this->createHouseholdForUser($user);
        $list = ShoppingList::factory()->create(['created_by' => $user->id, 'household_id' => $household->id]);
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        // Upload first image
        $image1 = UploadedFile::fake()->image('product1.jpg', 200, 200);
        $this->actingAs($user)->put(route('households.lists.items.update', [$household, $list, $item]), [
            'image_url' => $image1,
        ], ['Content-Type' => 'multipart/form-data']);

        $firstPath = $item->fresh()->image_url;
        $this->assertNotNull($firstPath);

        // Upload second image
        $image2 = UploadedFile::fake()->image('product2.jpg', 200, 200);
        $this->actingAs($user)->put(route('households.lists.items.update', [$household, $list, $item]), [
            'image_url' => $image2,
        ], ['Content-Type' => 'multipart/form-data']);

        $secondPath = $item->fresh()->image_url;
        $this->assertNotNull($secondPath);
        $this->assertNotEquals($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
    }
}
