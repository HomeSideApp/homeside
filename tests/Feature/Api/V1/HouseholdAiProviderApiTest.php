<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\AiProviderModule;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use HomeSide\AiAgents\Providers\ProviderTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdAiProviderApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Hogares']);
        Permission::create(['name' => 'view households', 'route_name' => 'households.index', 'description' => 'Ver hogares', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store households', 'route_name' => 'households.store', 'description' => 'Guardar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view household', 'route_name' => 'households.show', 'description' => 'Ver un hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update households', 'route_name' => 'households.update', 'description' => 'Actualizar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete households', 'route_name' => 'households.destroy', 'description' => 'Eliminar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'invite members', 'route_name' => 'households.invite', 'description' => 'Invitar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'remove members', 'route_name' => 'households.remove', 'description' => 'Eliminar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'switch household', 'route_name' => 'households.switch', 'description' => 'Cambiar hogar activo', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view ai providers', 'route_name' => 'households.ai-providers.index', 'description' => 'Ver proveedores IA del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'create ai providers', 'route_name' => 'households.ai-providers.store', 'description' => 'Crear proveedores IA del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update ai providers', 'route_name' => 'households.ai-providers.update', 'description' => 'Actualizar proveedores IA del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete ai providers', 'route_name' => 'households.ai-providers.destroy', 'description' => 'Eliminar proveedores IA del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'test ai providers', 'route_name' => 'households.ai-providers.test', 'description' => 'Probar conexión de proveedores IA', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'test ai provider config', 'route_name' => 'households.ai-providers.test-config', 'description' => 'Probar configuración IA', 'permission_group_id' => $group->id]);

        $this->role = Role::create(['name' => 'user', 'slug' => 'user']);
        $this->role->syncPermissions(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole($this->role);

        $this->household = Household::factory()->create([
            'created_by' => $this->user->id,
        ]);
        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_admin_can_create_provider(): void
    {
        $response = $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'OpenAI Principal',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key-12345',
            'module' => 'general',
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'base_url', 'model', 'module', 'enabled']]);

        $this->assertDatabaseHas('ai_providers', [
            'household_id' => $this->household->id,
            'name' => 'OpenAI Principal',
            'module' => 'general',
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
        ]);
    }

    public function test_provider_temperature_must_be_between_zero_and_two(): void
    {
        $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'Invalid temperature',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test-key-12345',
            'module' => 'general',
            'configuration' => ['temperature' => 2.1],
        ])->assertUnprocessable()->assertJsonValidationErrors('configuration.temperature');
    }

    public function test_member_cannot_create_provider(): void
    {
        $member = User::factory()->create();
        $member->assignRole($this->role);
        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id' => $member->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($member);

        $response = $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'Test Provider',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-test',
            'module' => 'general',
        ]);

        $response->assertForbidden();
    }

    public function test_provider_api_key_is_encrypted_in_database(): void
    {
        $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'Encrypted Test',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-secret-key-abcdef',
            'module' => 'general',
        ]);

        $provider = AiProvider::where('household_id', $this->household->id)->first();

        $this->assertNotEquals('sk-secret-key-abcdef', $provider->getRawOriginal('api_key'));
        $this->assertEquals('sk-secret-key-abcdef', $provider->api_key);
    }

    public function test_provider_resource_never_exposes_api_key(): void
    {
        $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'No Key Expose',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-secret-key',
            'module' => 'general',
        ]);

        $response = $this->getJson(route('api.v1.households.ai-providers.index', $this->household));

        $response->assertOk()
            ->assertJsonMissing(['api_key', 'api_key_encrypted']);
    }

    public function test_user_cannot_access_other_household_provider(): void
    {
        $otherHousehold = Household::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->assignRole($this->role);
        HouseholdMember::create([
            'household_id' => $otherHousehold->id,
            'user_id' => $otherUser->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $provider = AiProvider::factory()->create([
            'household_id' => $otherHousehold->id,
            'created_by' => $otherUser->id,
        ]);

        // El usuario actual (de $this->household) intenta listar providers del otro household
        $response = $this->getJson(route('api.v1.households.ai-providers.index', $otherHousehold));

        $response->assertForbidden();
    }

    public function test_admin_can_update_provider(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson(route('api.v1.households.ai-providers.update', [$this->household, $provider]), [
            'name' => 'Updated Provider',
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-new-key',
            'module' => $provider->module,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Provider');

        $this->assertDatabaseHas('ai_providers', [
            'id' => $provider->id,
            'name' => 'Updated Provider',
        ]);
    }

    public function test_admin_can_delete_provider(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson(route('api.v1.households.ai-providers.destroy', [$this->household, $provider]));

        $response->assertNoContent();
        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_can_create_two_providers_for_same_module(): void
    {
        $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'First Provider',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-key-1',
            'module' => 'general',
        ]);

        $response = $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'Second Provider',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-key-2',
            'module' => 'general',
        ]);

        $response->assertCreated();
        $this->assertSame(2, AiProvider::query()->where('household_id', $this->household->id)->forModule('general')->count());
    }

    public function test_admin_can_test_provider_connection(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
        ]);

        $testerMock = \Mockery::mock(AiProviderTester::class);
        $testerMock->shouldReceive('testProvider')
            ->once()
            ->andReturn(ProviderTestData::ok(150, 'Conexión exitosa'));

        $this->app->instance(AiProviderTester::class, $testerMock);

        $response = $this->postJson(route('api.v1.households.ai-providers.test', [$this->household, $provider]));

        $response->assertOk()
            ->assertJsonStructure(['status', 'latency_ms']);
    }

    public function test_admin_can_test_unsaved_provider_configuration(): void
    {
        $tester = $this->mock(AiProviderTester::class);
        $tester->shouldReceive('testConfig')
            ->once()
            ->with([
                'type' => 'openai-compatible',
                'base_url' => 'https://ai.example.test/v1',
                'model' => 'test-model',
                'api_key' => 'secret',
            ])
            ->andReturn(ProviderTestData::ok(12, 'ok'));

        $this->postJson(route('api.v1.households.ai-providers.test-config', $this->household), [
            'type' => 'openai-compatible',
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'test-model',
            'api_key' => 'secret',
        ])->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_admin_can_mark_provider_as_default_through_api(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
            'is_default' => false,
        ]);

        $this->postJson(route('api.v1.households.ai-providers.default', [$this->household, $provider]))
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertTrue($provider->fresh()->is_default);
    }

    public function test_admin_can_update_household_module_config_through_api(): void
    {
        $this->putJson(route('api.v1.households.ai-providers.module-config', $this->household), [
            'module' => AiProviderModule::Economy->value,
            'agent_name' => 'ticket_analyzer',
            'label' => 'Ticket analyzer',
            'additional_instructions' => 'Extract transactions carefully.',
            'enabled' => true,
        ])->assertOk()
            ->assertJsonPath('data.agent_name', 'ticket_analyzer');

        $this->assertDatabaseHas('module_ai_configurations', [
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Economy->value,
            'agent_name' => 'ticket_analyzer',
        ]);
    }

    public function test_returns_404_for_provider_from_another_route_household(): void
    {
        $otherHousehold = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'household_id' => $otherHousehold->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);
        $provider = AiProvider::factory()->create([
            'household_id' => $otherHousehold->id,
            'created_by' => $this->user->id,
        ]);

        $this->getJson(route('api.v1.households.ai-providers.show', [$this->household, $provider]))
            ->assertNotFound();
    }

    public function test_provider_module_must_be_valid_enum(): void
    {
        $response = $this->postJson(route('api.v1.households.ai-providers.store', $this->household), [
            'name' => 'Invalid Module',
            'type' => 'openai-compatible',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'api_key' => 'sk-key',
            'module' => 'invalid-module',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['module']);
    }
}
