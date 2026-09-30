<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Productos']);
        $permission = Permission::create([
            'name' => 'quick create products',
            'route_name' => 'households.products.quick-create',
            'description' => 'Creación rápida de productos',
            'permission_group_id' => $group->id,
        ]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->givePermissionTo([$permission]);

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
    }

    public function test_quick_create_creates_product(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('households.products.quick-create', $this->household), ['name' => 'Leche semi']);

        $response->assertOk()
            ->assertJsonPath('name', 'Leche semi')
            ->assertJsonPath('slug', 'leche-semi');
    }

    public function test_quick_create_generates_slug(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('households.products.quick-create', $this->household), ['name' => 'Arroz con pollo']);

        $response->assertOk()
            ->assertJsonPath('slug', 'arroz-con-pollo');
    }

    public function test_quick_create_does_not_duplicate(): void
    {
        Product::create(['name' => 'Manzana', 'slug' => 'manzana', 'created_by' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson(route('households.products.quick-create', $this->household), ['name' => 'Manzana']);

        $response->assertOk();

        $this->assertEquals(1, Product::where('slug', 'manzana')->count());
    }

    public function test_quick_create_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('households.products.quick-create', $this->household), ['name' => '']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_quick_create_returns_category_when_set(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('households.products.quick-create', $this->household), ['name' => 'Test Product']);

        $response->assertOk()
            ->assertJsonPath('category', null);
    }
}
