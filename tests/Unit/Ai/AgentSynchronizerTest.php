<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Agents\RecipeGeneratorAgent;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiAgent;
use HomeSide\AiAgents\Synchronizer\AgentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private AgentRegistry $registry;

    private AgentSynchronizer $synchronizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(AgentRegistry::class);
        $this->synchronizer = app(AgentSynchronizer::class);
    }

    public function test_sync_creates_rows_for_registered_agents(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);

        $result = $this->synchronizer->sync();

        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertDatabaseHas('ai_agents', ['key' => 'test.agent']);
    }

    public function test_sync_is_idempotent(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);

        $this->synchronizer->sync();
        $result = $this->synchronizer->sync();

        $this->assertSame(0, $result['created']);
        $this->assertGreaterThanOrEqual(1, $result['unchanged']);
    }

    public function test_sync_detects_orphaned_rows(): void
    {
        // Create a row in the DB without a matching registered class.
        AiAgent::factory()->create(['key' => 'orphaned.agent']);

        $result = $this->synchronizer->sync();

        $this->assertContains('orphaned.agent', $result['orphaned_keys']);
    }

    public function test_sync_populates_label_from_agent_metadata(): void
    {
        $this->registry->register('recipes.recipe_generator', RecipeGeneratorAgent::class);

        $result = $this->synchronizer->sync();

        // The agent was created (or already existed from the service provider).
        $row = AiAgent::findByKey('recipes.recipe_generator');
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->label);
    }

    public function test_diagnose_returns_clean_when_synchronised(): void
    {
        $this->registry->register('test.agent', FakeAgent::class);
        $this->synchronizer->sync();

        $diagnosis = $this->synchronizer->diagnose();

        $this->assertEmpty($diagnosis['classes_without_rows']);
        $this->assertEmpty($diagnosis['rows_without_classes']);
    }

    public function test_diagnose_detects_classes_without_rows(): void
    {
        $this->registry->register('test.unsynced', FakeAgent::class);

        $diagnosis = $this->synchronizer->diagnose();

        $this->assertArrayHasKey('test.unsynced', $diagnosis['classes_without_rows']);
    }

    public function test_diagnose_detects_rows_without_classes(): void
    {
        AiAgent::factory()->create(['key' => 'orphaned.agent']);

        $diagnosis = $this->synchronizer->diagnose();

        $this->assertArrayHasKey('orphaned.agent', $diagnosis['rows_without_classes']);
    }
}
