<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminAiUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);
        Permission::create(['name' => 'view ai usage', 'route_name' => 'admin.ai-usage', 'description' => 'Ver uso de IA', 'permission_group_id' => $adminGroup->id]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());
        $admin->assignRole($role);

        return $admin;
    }

    public function test_admin_can_access_usage_page(): void
    {
        $admin = $this->createAdmin();
        Cache::flush();

        $response = $this->actingAs($admin)->get(route('admin.ai-usage'));

        $response->assertOk();
    }

    public function test_regular_user_cannot_access_usage_page(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('admin.ai-usage'));

        $response->assertForbidden();
    }

    public function test_usage_page_shows_summary(): void
    {
        $admin = $this->createAdmin();
        Cache::flush();

        AiRun::create([
            'user_id' => $admin->id,
            'agent' => 'recipes.recipe_generator',
            'provider_name' => 'openai',
            'model_name' => 'gpt-4o',
            'input_tokens' => 100,
            'output_tokens' => 200,
            'duration_ms' => 1500,
            'status' => 'ok',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-usage'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->has('summary'));
    }
}
