<?php

namespace Tests\Feature\Admin;

use App\Models\Household;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiProviderTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionGroup $adminGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminGroup = PermissionGroup::create(['name' => 'Admin']);

        Permission::create(['name' => 'view global ai providers', 'route_name' => 'admin.ai-providers', 'description' => 'Ver proveedores IA globales', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'create global ai providers', 'route_name' => 'admin.ai-providers.store', 'description' => 'Crear proveedores IA globales', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'update global ai providers', 'route_name' => 'admin.ai-providers.update', 'description' => 'Actualizar proveedores IA globales', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'delete global ai providers', 'route_name' => 'admin.ai-providers.destroy', 'description' => 'Eliminar proveedores IA globales', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'test global ai providers', 'route_name' => 'admin.ai-providers.test', 'description' => 'Probar conexión de proveedores IA globales', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'default global ai providers', 'route_name' => 'admin.ai-providers.default', 'description' => 'Marcar proveedor IA global como predeterminado', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'test config global ai providers', 'route_name' => 'admin.ai-providers.test-config', 'description' => 'Probar configuración de proveedores IA globales sin guardar', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'update global ai prompt', 'route_name' => 'admin.ai-settings.global-prompt', 'description' => 'Actualizar prompt global IA', 'permission_group_id' => $this->adminGroup->id]);
        Permission::create(['name' => 'update module ai prompt', 'route_name' => 'admin.ai-settings.module-prompt', 'description' => 'Actualizar prompt por módulo IA', 'permission_group_id' => $this->adminGroup->id]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());
        $admin->assignRole($role);

        return $admin;
    }

    private function createRegularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($role);

        return $user;
    }

    // === CRUD Tests ===

    public function test_admin_can_access_index_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.ai-providers'));

        $response->assertOk();
    }

    public function test_admin_can_create_global_provider(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.ai-providers.store'), [
            'name' => 'Global OpenAI',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key',
            'module' => 'general',
            'enabled' => true,
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
            'reasoning' => true,
            'temperature' => true,
            'context_window' => 128000,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ai_providers', [
            'name' => 'Global OpenAI',
            'household_id' => null,
            'module' => 'general',
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
            'context_window' => 128000,
        ]);
    }

    public function test_admin_can_assign_a_global_provider_to_multiple_modules(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.ai-providers.store'), [
            'name' => 'Shared provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key',
            'modules' => ['recipes', 'economy'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $provider = AiProvider::query()->where('name', 'Shared provider')->sole();
        $this->assertSame(['economy', 'recipes'], $provider->assignedModules());
    }

    public function test_admin_can_update_global_provider(): void
    {
        $admin = $this->createAdmin();
        $provider = AiProvider::create([
            'name' => 'Old Name',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-old-key',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $admin->id,
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.ai-providers.update', $provider), [
            'name' => 'Updated Name',
            'type' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => 'claude-3-opus',
            'api_key' => 'sk-new-key',
            'module' => 'recipes',
            'enabled' => false,
            'attachment' => false,
            'tool_call' => false,
            'structured_output' => false,
            'context_window' => 32000,
            'configuration' => ['temperature' => 0.3],
        ]);

        $response->assertRedirect();

        $provider->refresh();
        $this->assertEquals('Updated Name', $provider->name);
        $this->assertEquals('anthropic', $provider->type);
        $this->assertEquals('recipes', $provider->module);
        $this->assertFalse($provider->attachment);
        $this->assertFalse($provider->tool_call);
        $this->assertFalse($provider->structured_output);
        $this->assertSame(32000, $provider->context_window);
        $this->assertSame(0.3, $provider->configuration['temperature']);
    }

    public function test_admin_can_update_global_provider_without_replacing_api_key(): void
    {
        $admin = $this->createAdmin();
        $provider = AiProvider::create([
            'name' => 'Original Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-original-key',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.ai-providers.update', $provider), [
            'name' => 'Updated Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4.1',
            'api_key' => '',
            'module' => 'general',
            'enabled' => true,
        ]);

        $response->assertRedirect();

        $provider->refresh();
        $this->assertSame('Updated Provider', $provider->name);
        $this->assertSame('sk-original-key', $provider->api_key);
    }

    public function test_admin_can_delete_global_provider(): void
    {
        $admin = $this->createAdmin();
        $provider = AiProvider::create([
            'name' => 'To Delete',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.ai-providers.destroy', $provider));

        $response->assertRedirect();
        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_admin_can_mark_provider_as_default(): void
    {
        $admin = $this->createAdmin();
        $provider = AiProvider::create([
            'name' => 'Default Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'is_default' => false,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.ai-providers.default', $provider));

        $response->assertRedirect();
        $provider->refresh();
        $this->assertTrue($provider->is_default);
    }

    // === Authorization Tests (IDOR) ===

    public function test_regular_user_cannot_access_admin_ai_providers(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->get(route('admin.ai-providers'));

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_create_global_provider(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->post(route('admin.ai-providers.store'), [
            'name' => 'Should Fail',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
        ]);

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_update_global_provider(): void
    {
        $user = $this->createRegularUser();
        $provider = AiProvider::create([
            'name' => 'Protected',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.ai-providers.update', $provider), [
            'name' => 'Hacked',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
        ]);

        $response->assertForbidden();
    }

    public function test_regular_user_cannot_delete_global_provider(): void
    {
        $user = $this->createRegularUser();
        $provider = AiProvider::create([
            'name' => 'Protected',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.ai-providers.destroy', $provider));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_admin_ai_providers(): void
    {
        $response = $this->get(route('admin.ai-providers'));

        $response->assertRedirect('/login');
    }

    // === Global Settings Tests ===

    public function test_admin_can_update_global_prompt(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.ai-settings.global-prompt'), [
            'extra_prompt' => 'Always respond in Spanish.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ai_global_settings', [
            'module' => null,
            'extra_prompt' => 'Always respond in Spanish.',
        ]);
    }

    public function test_admin_can_update_module_prompt(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->put(route('admin.ai-settings.module-prompt'), [
            'module' => 'recipes',
            'extra_prompt' => 'Focus on Spanish cuisine.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('ai_global_settings', [
            'module' => 'recipes',
            'extra_prompt' => 'Focus on Spanish cuisine.',
        ]);
    }

    public function test_regular_user_cannot_update_global_prompt(): void
    {
        $user = $this->createRegularUser();

        $response = $this->actingAs($user)->put(route('admin.ai-settings.global-prompt'), [
            'extra_prompt' => 'Should fail.',
        ]);

        $response->assertForbidden();
    }

    // === Household IDOR Tests ===

    public function test_household_user_cannot_modify_global_provider(): void
    {
        $household = Household::create(['name' => 'Test Household', 'invite_code' => 'TEST1234', 'created_by' => User::factory()->create()->id]);
        $member = User::factory()->create();
        $household->members()->create(['user_id' => $member->id, 'role' => 'admin', 'joined_at' => now()]);

        $globalProvider = AiProvider::create([
            'name' => 'Global Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // Household admin should NOT be able to manage global providers
        $response = $this->actingAs($member)->put(route('admin.ai-providers.update', $globalProvider), [
            'name' => 'Hacked',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
        ]);

        $response->assertForbidden();
    }

    public function test_global_provider_is_independent_of_households(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.ai-providers.store'), [
            'name' => 'Global Fallback',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'recipes',
            'enabled' => true,
        ]);

        $provider = AiProvider::where('name', 'Global Fallback')->first();
        $this->assertNotNull($provider);
        $this->assertNull($provider->household_id);
        $this->assertTrue($provider->isGlobal());
    }

    public function test_global_providers_listed_in_index(): void
    {
        $admin = $this->createAdmin();

        AiProvider::create([
            'name' => 'Global 1',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
            'enabled' => true,
            'created_by' => $admin->id,
        ]);

        AiProvider::create([
            'name' => 'Global 2',
            'type' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => 'claude-3',
            'api_key' => 'sk-test2',
            'module' => 'recipes',
            'enabled' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-providers'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/AiProviders/Index'));
    }
}
