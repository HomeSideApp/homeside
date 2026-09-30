<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AiProviderModule;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\ModuleAiConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HouseholdAiProviderWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    /**
     * Prepare a household administrator with the shared AI provider update permission.
     *
     * @return void No value is returned.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $permissionGroup = PermissionGroup::create(['name' => 'Households']);
        $updatePermission = Permission::create([
            'name' => 'Update household AI providers',
            'route_name' => 'households.ai-providers.update',
            'description' => 'Update household AI providers and their module configuration.',
            'permission_group_id' => $permissionGroup->id,
        ]);

        $role = Role::create(['name' => 'Household administrator', 'slug' => 'household-administrator']);
        $role->givePermissionTo([$updatePermission]);

        $this->user = User::factory()->create();
        $this->user->assignRole($role);
        $this->household = Household::factory()->create(['created_by' => $this->user->id]);

        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);
    }

    /**
     * Verify that the default-provider route reuses the provider update permission.
     *
     * @return void No value is returned.
     */
    public function test_household_admin_can_mark_provider_as_default_with_update_permission(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->post(route('households.ai-providers.default', [
            $this->household,
            $provider,
        ]));

        $response->assertRedirect();
        $this->assertTrue($provider->fresh()->is_default);
    }

    public function test_household_admin_can_edit_model_capabilities_without_changing_the_api_key(): void
    {
        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
            'module' => AiProviderModule::General,
            'attachment' => true,
            'tool_call' => true,
            'structured_output' => true,
        ]);
        $apiKey = $provider->api_key;

        $this->actingAs($this->user)->put(route('households.ai-providers.update', [
            $this->household,
            $provider,
        ]), [
            'name' => $provider->name,
            'type' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => $provider->model,
            'modules' => ['general'],
            'api_key' => '',
            'attachment' => false,
            'tool_call' => false,
            'structured_output' => false,
            'reasoning' => true,
            'temperature' => true,
            'context_window' => 64000,
            'max_output_tokens' => 8192,
            'configuration' => [
                'temperature' => 0.7,
                'model_capabilities' => ['context_tokens' => 64000],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertFalse($provider->attachment);
        $this->assertFalse($provider->tool_call);
        $this->assertFalse($provider->structured_output);
        $this->assertTrue($provider->reasoning);
        $this->assertTrue($provider->temperature);
        $this->assertSame(64000, $provider->context_window);
        $this->assertSame(8192, $provider->max_output_tokens);
        $this->assertSame(0.7, $provider->configuration['temperature']);
        $this->assertSame(64000, $provider->configuration['model_capabilities']['context_tokens']);
        $this->assertSame($apiKey, $provider->api_key);
    }

    /**
     * Verify that the module configuration route reuses the provider update permission.
     *
     * @return void No value is returned.
     */
    public function test_household_admin_can_update_module_config_with_update_permission(): void
    {
        $response = $this->actingAs($this->user)->put(route(
            'households.ai-providers.module-config',
            $this->household,
        ), [
            'module' => AiProviderModule::Economy->value,
            'agent_name' => 'ticket_analyzer',
            'label' => 'Ticket analyzer',
            'additional_instructions' => 'Analyze the supplied economic document.',
            'description' => 'Extracts transactions from economic documents.',
            'ai_provider_id' => null,
            'model' => null,
            'parameters' => [
                'temperature' => 0.2,
                'max_tokens' => 2048,
            ],
            'enabled' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('module_ai_configurations', [
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Economy->value,
            'agent_name' => 'ticket_analyzer',
            'label' => 'Ticket analyzer',
            'enabled' => true,
        ]);
    }

    public function test_legacy_household_prompt_migration_preserves_custom_edits_only(): void
    {
        $defaultPrompt = app(AgentRegistry::class)
            ->getModules()['economy']->agents()['ticket_analyzer']['system_prompt'];

        ModuleAiConfiguration::query()->create([
            'household_id' => $this->household->id,
            'module' => 'economy',
            'agent_name' => 'ticket_analyzer',
            'label' => 'Ticket analyzer',
            'system_prompt' => $defaultPrompt,
        ]);
        ModuleAiConfiguration::query()->create([
            'household_id' => $this->household->id,
            'module' => 'recipes',
            'agent_name' => 'recipe_generator',
            'label' => 'Recipe generator',
            'system_prompt' => 'Mi preferencia personal',
        ]);

        $migration = require base_path('database/migrations/2026_09_17_233111_migrate_household_ai_prompts_to_additional_instructions.php');
        $migration->up();

        $this->assertDatabaseHas('module_ai_configurations', [
            'module' => 'economy',
            'agent_name' => 'ticket_analyzer',
            'additional_instructions' => null,
        ]);
        $this->assertDatabaseHas('module_ai_configurations', [
            'module' => 'recipes',
            'agent_name' => 'recipe_generator',
            'additional_instructions' => 'Mi preferencia personal',
        ]);
    }
}
