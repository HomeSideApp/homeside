<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Contracts\HomeSideAgent;
use HomeSide\AiAgents\AgentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRegistryTest extends TestCase
{
    use RefreshDatabase;

    private AgentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(AgentRegistry::class);
    }

    public function test_register_and_has_agent(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);

        $this->assertTrue($this->registry->has('test.agent'));
        $this->assertFalse($this->registry->has('nonexistent.agent'));
    }

    public function test_get_resolves_instance_from_container(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);

        $agent = $this->registry->get('test.agent');

        $this->assertInstanceOf(HomeSideAgent::class, $agent);
        $this->assertInstanceOf(FakeAgent::class, $agent);
    }

    public function test_get_throws_for_unregistered_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registry->get('nonexistent.agent');
    }

    public function test_all_returns_all_keys(): void
    {
        $this->registry->register('recipes.generator', FakeAgent::class);
        $this->registry->register('recipes.suggester', FakeAgent::class);
        $this->registry->register('economy.analyzer', FakeAgent::class);

        $all = $this->registry->all();

        $this->assertContains('recipes.generator', $all);
        $this->assertContains('recipes.suggester', $all);
        $this->assertContains('economy.analyzer', $all);
        $this->assertGreaterThanOrEqual(3, count($all));
    }

    public function test_for_module_filters_by_prefix(): void
    {
        $this->registry->register('recipes.generator', FakeAgent::class);
        $this->registry->register('recipes.suggester', FakeAgent::class);
        $this->registry->register('economy.analyzer', FakeAgent::class);

        $recipeAgents = $this->registry->forModule('recipes');
        $economyAgents = $this->registry->forModule('economy');

        $this->assertGreaterThanOrEqual(2, count($recipeAgents));
        $this->assertContains('recipes.generator', $recipeAgents);
        $this->assertContains('recipes.suggester', $recipeAgents);

        // economy.analyzer del test + economy.ticket_analyzer registrado por AiModuleServiceProvider
        $this->assertGreaterThanOrEqual(1, count($economyAgents));
        $this->assertContains('economy.analyzer', $economyAgents);
    }

    public function test_get_class_returns_class_string(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);

        $this->assertSame(FakeAgent::class, $this->registry->getClass('test.agent'));
        $this->assertNull($this->registry->getClass('nonexistent.agent'));
    }
}
