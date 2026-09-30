<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Modules\EconomyAiModule;
use App\Ai\Modules\RecipesAiModule;
use App\Contracts\ModuleAiProvider;
use App\Enums\AiProviderModule;
use HomeSide\AiAgents\AgentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRegistryModuleTest extends TestCase
{
    use RefreshDatabase;

    private AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(AgentRegistry::class);
    }

    public function test_register_module_and_retrieve(): void
    {
        $this->registry->registerModule(new RecipesAiModule);

        $module = $this->registry->getModule('recipes');

        $this->assertNotNull($module);
        $this->assertInstanceOf(ModuleAiProvider::class, $module);
        $this->assertSame(AiProviderModule::Recipes->value, $module->module());
    }

    public function test_get_modules_returns_all_registered(): void
    {
        $this->registry->registerModule(new EconomyAiModule);
        $this->registry->registerModule(new RecipesAiModule);

        $modules = $this->registry->getModules();

        $this->assertGreaterThanOrEqual(2, count($modules));
        $this->assertArrayHasKey('economy', $modules);
        $this->assertArrayHasKey('recipes', $modules);
    }

    public function test_get_module_returns_null_for_unregistered(): void
    {
        $module = $this->registry->getModule('nonexistent');

        $this->assertNull($module);
    }

    public function test_module_agents_are_accessible(): void
    {
        $this->registry->registerModule(new RecipesAiModule);

        $module = $this->registry->getModule('recipes');
        $agents = $module->agents();

        $this->assertNotEmpty($agents);
        $this->assertArrayHasKey('recipe_generator', $agents);
        $this->assertArrayHasKey('ingredient_suggester', $agents);
        $this->assertArrayHasKey('label', $agents['recipe_generator']);
        $this->assertArrayHasKey('system_prompt', $agents['recipe_generator']);
    }

    public function test_register_same_module_replaces_previous(): void
    {
        $this->registry->registerModule(new RecipesAiModule);
        $replacement = new RecipesAiModule;

        $this->registry->registerModule($replacement);

        $this->assertSame($replacement, $this->registry->getModule('recipes'));
    }
}
