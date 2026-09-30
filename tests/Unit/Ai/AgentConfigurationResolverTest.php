<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Configuration\AgentConfigurationResolver;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Models\AiAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentConfigurationResolverTest extends TestCase
{
    use RefreshDatabase;

    private AgentConfigurationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AgentConfigurationResolver::class);
    }

    public function test_resolve_throws_when_no_agent_row_exists(): void
    {
        $context = new AiExecutionContextData(userId: 'user-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->resolve('nonexistent.agent', $context);
    }

    public function test_resolve_returns_agent_defaults(): void
    {
        AiAgent::factory()->create([
            'key' => 'recipes.recipe_generator',
            'enabled' => true,
            'parameters' => null,
        ]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $config = $this->resolver->resolve('recipes.recipe_generator', $context);

        $this->assertSame('recipes.recipe_generator', $config->agent);
        $this->assertTrue($config->enabled);
        $this->assertArrayHasKey('temperature', $config->parameters);
        $this->assertArrayHasKey('max_tokens', $config->parameters);
    }

    public function test_resolve_merges_agent_class_defaults(): void
    {
        AiAgent::factory()->create([
            'key' => 'recipes.recipe_generator',
            'enabled' => true,
            'parameters' => ['temperature' => 0.9],
        ]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $config = $this->resolver->resolve('recipes.recipe_generator', $context);

        // The ai_agent row overrides the class default of 0.7.
        $this->assertSame(0.9, $config->parameters['temperature']);
        // But max_tokens comes from the class default.
        $this->assertSame(4096, $config->parameters['max_tokens']);
    }

    public function test_resolve_respects_enabled_flag(): void
    {
        AiAgent::factory()->create([
            'key' => 'recipes.recipe_generator',
            'enabled' => false,
        ]);

        $context = new AiExecutionContextData(userId: 'user-1');
        $config = $this->resolver->resolve('recipes.recipe_generator', $context);

        $this->assertFalse($config->enabled);
    }

    public function test_get_platform_prompt_returns_prompt(): void
    {
        AiAgent::factory()->create([
            'key' => 'recipes.recipe_generator',
            'platform_prompt' => 'Eres un chef experto.',
        ]);

        $prompt = $this->resolver->getPlatformPrompt('recipes.recipe_generator');

        $this->assertSame('Eres un chef experto.', $prompt);
    }

    public function test_get_platform_prompt_throws_for_missing_agent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->getPlatformPrompt('nonexistent.agent');
    }

    public function test_get_household_instructions_returns_null_without_household(): void
    {
        $result = $this->resolver->getHouseholdInstructions(
            'recipes.recipe_generator',
            null,
        );

        $this->assertNull($result);
    }
}
