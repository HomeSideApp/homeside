<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureModuleEnabledTest extends TestCase
{
    use RefreshDatabase;

    protected Household $household;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions for lists
        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'index lists', 'route_name' => 'households.lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);

        // Create role with permissions
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['index lists']);

        // Create user and household
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');

        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    public function test_allows_access_when_module_is_enabled(): void
    {
        // Factory already creates all modules enabled, just ensure shopping_lists is enabled
        $this->household->modules()->updateOrCreate(
            ['module' => 'shopping_lists'],
            ['enabled' => true]
        );

        // Create a list so the controller has data to render
        ShoppingList::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->user)
            ->get("/households/{$this->household->id}/lists");

        $response->assertSuccessful();
    }

    public function test_blocks_access_when_module_is_disabled(): void
    {
        $this->household->modules()->updateOrCreate(
            ['module' => 'shopping_lists'],
            ['enabled' => false]
        );

        $this->actingAs($this->user)
            ->get("/households/{$this->household->id}/lists")
            ->assertNotFound();
    }

    public function test_blocks_access_when_module_does_not_exist(): void
    {
        // Delete all modules so none exist
        $this->household->modules()->delete();

        $this->actingAs($this->user)
            ->get("/households/{$this->household->id}/lists")
            ->assertNotFound();
    }

    public function test_blocks_api_access_when_module_disabled(): void
    {
        $this->household->modules()->updateOrCreate(
            ['module' => 'shopping_lists'],
            ['enabled' => false]
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/lists")
            ->assertNotFound();
    }

    public function test_allows_api_access_when_module_enabled(): void
    {
        $this->household->modules()->updateOrCreate(
            ['module' => 'shopping_lists'],
            ['enabled' => true]
        );

        $this->withoutExceptionHandling();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/households/{$this->household->id}/lists");

        $response->assertSuccessful();
    }
}
