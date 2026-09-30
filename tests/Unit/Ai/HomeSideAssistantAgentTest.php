<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Agents\HomeSideAssistantAgent;
use App\Ai\Contracts\HomeSideAgent;
use App\Models\User;
use HomeSide\AiAgents\Configuration\Capability;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class HomeSideAssistantAgentTest extends TestCase
{
    private HomeSideAssistantAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = new HomeSideAssistantAgent;
    }

    #[Test]
    public function implements_agent_interface(): void
    {
        $this->assertInstanceOf(Agent::class, $this->agent);
    }

    #[Test]
    public function implements_has_tools(): void
    {
        $this->assertInstanceOf(HasTools::class, $this->agent);
    }

    #[Test]
    public function implements_home_side_agent(): void
    {
        $this->assertInstanceOf(HomeSideAgent::class, $this->agent);
    }

    #[Test]
    public function key_is_correct(): void
    {
        $this->assertSame('assistant.homeside', $this->agent->key());
    }

    #[Test]
    public function module_is_assistant(): void
    {
        $this->assertSame('assistant', $this->agent->module());
    }

    #[Test]
    public function required_capabilities_include_text_and_tools(): void
    {
        $caps = $this->agent->requiredCapabilities();

        $this->assertContains(Capability::Text, $caps);
        $this->assertContains(Capability::Tools, $caps);
    }

    #[Test]
    public function tools_returns_array_of_tools(): void
    {
        $this->agent->setExecutionContext(new AiExecutionContextData(
            userId: User::factory()->create()->id,
        ));

        $tools = $this->agent->tools();

        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(Tool::class, $tool);
        }
    }

    #[Test]
    public function tools_reject_an_empty_execution_context(): void
    {
        $this->agent->setExecutionContext(new AiExecutionContextData(userId: ''));

        $this->expectException(RuntimeException::class);

        $this->agent->tools();
    }

    #[Test]
    public function context_providers_include_user_and_household(): void
    {
        $providers = $this->agent->contextProviders();

        $this->assertContains('user', $providers);
        $this->assertContains('household', $providers);
    }

    #[Test]
    public function instructions_mention_no_direct_mutation(): void
    {
        $instructions = (string) $this->agent->instructions();

        $this->assertStringContainsString('NUNCA', $instructions);
        $this->assertStringContainsString('propuesta', $instructions);
    }

    #[Test]
    public function runtime_configuration_reaches_sdk_option_methods(): void
    {
        $this->agent->setRuntimeConfiguration([
            'max_tokens' => 4096,
            'temperature' => 0.2,
            'max_steps' => 3,
            'top_p' => 0.8,
        ]);

        $this->assertSame(4096, $this->agent->maxTokens());
        $this->assertSame(0.2, $this->agent->temperature());
        $this->assertSame(3, $this->agent->maxSteps());
        $this->assertSame(0.8, $this->agent->topP());
    }

    #[Test]
    public function composed_runtime_prompt_replaces_the_class_default_for_the_sdk(): void
    {
        $this->agent->useRuntimeInstructions('Platform rules followed by household preferences.');

        $this->assertSame('Platform rules followed by household preferences.', $this->agent->instructions());
    }
}
