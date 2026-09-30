<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Models\AiAgent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_agent_with_required_fields(): void
    {
        $agent = AiAgent::factory()->create([
            'key' => 'recipes.recipe_generator',
            'module' => 'recipes',
            'label' => 'Generador de recetas',
            'platform_prompt' => 'Eres un chef experto.',
            'prompt_version' => 1,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('ai_agents', [
            'key' => 'recipes.recipe_generator',
            'module' => 'recipes',
            'enabled' => true,
        ]);

        $this->assertSame('recipes.recipe_generator', $agent->key);
        $this->assertSame('recipes', $agent->module);
        $this->assertTrue($agent->enabled);
        $this->assertSame(1, $agent->prompt_version);
    }

    public function test_find_by_key_returns_matching_agent(): void
    {
        AiAgent::factory()->create(['key' => 'economy.ticket_analyzer']);

        $found = AiAgent::findByKey('economy.ticket_analyzer');

        $this->assertNotNull($found);
        $this->assertSame('economy.ticket_analyzer', $found->key);
    }

    public function test_find_by_key_returns_null_for_missing(): void
    {
        $found = AiAgent::findByKey('nonexistent.agent');

        $this->assertNull($found);
    }

    public function test_scope_enabled_filters_disabled_agents(): void
    {
        AiAgent::factory()->enabled()->create(['key' => 'recipes.recipe_generator']);
        AiAgent::factory()->disabled()->create(['key' => 'economy.ticket_analyzer']);

        $enabled = AiAgent::enabled()->get();

        $this->assertCount(1, $enabled);
        $this->assertSame('recipes.recipe_generator', $enabled->first()->key);
    }

    public function test_scope_for_module_filters_by_module(): void
    {
        AiAgent::factory()->create(['key' => 'recipes.recipe_generator', 'module' => 'recipes']);
        AiAgent::factory()->create(['key' => 'recipes.ingredient_suggester', 'module' => 'recipes']);
        AiAgent::factory()->create(['key' => 'economy.ticket_analyzer', 'module' => 'economy']);

        $recipeAgents = AiAgent::forModule('recipes')->get();

        $this->assertCount(2, $recipeAgents);
    }

    public function test_parameters_are_cast_to_array(): void
    {
        $agent = AiAgent::factory()->create([
            'parameters' => ['temperature' => 0.7, 'max_tokens' => 4096],
        ]);

        $this->assertIsArray($agent->parameters);
        $this->assertSame(0.7, $agent->parameters['temperature']);
        $this->assertSame(4096, $agent->parameters['max_tokens']);
    }

    public function test_key_is_unique(): void
    {
        AiAgent::factory()->create(['key' => 'recipes.recipe_generator']);

        $this->expectException(QueryException::class);

        AiAgent::factory()->create(['key' => 'recipes.recipe_generator']);
    }
}
