<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Agents\TranslationGeneratorAgent;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Synchronizer\AgentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationGeneratorAgentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_the_translations_generator_row(): void
    {
        $registry = app(AgentRegistry::class);
        $registry->register('translations.generator', TranslationGeneratorAgent::class);

        $result = app(AgentSynchronizer::class)->sync();

        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertDatabaseHas('ai_agents', ['key' => 'translations.generator']);
    }

    public function test_sync_is_idempotent_for_the_translations_generator(): void
    {
        $registry = app(AgentRegistry::class);
        $registry->register('translations.generator', TranslationGeneratorAgent::class);

        app(AgentSynchronizer::class)->sync();
        $result = app(AgentSynchronizer::class)->sync();

        $this->assertSame(0, $result['created']);
        $this->assertGreaterThanOrEqual(1, $result['unchanged']);
    }

    public function test_agent_declares_the_translations_module_and_capabilities(): void
    {
        $agent = new TranslationGeneratorAgent;

        $this->assertSame('translations.generator', $agent->key());
        $this->assertSame('translations', $agent->module());
        $this->assertSame(1, $agent->version());
        $this->assertSame([], $agent->contextProviders());
        $this->assertSame(0.2, $agent->defaultConfiguration()['temperature']);
        $this->assertSame(2048, $agent->defaultConfiguration()['max_tokens']);
        $this->assertSame(90, $agent->defaultConfiguration()['timeout']);
        $this->assertSame('Translation generator', $agent->label());
    }
}
