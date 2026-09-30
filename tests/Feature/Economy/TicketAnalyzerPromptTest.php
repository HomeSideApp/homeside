<?php

namespace Tests\Feature\Economy;

use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\AiAgentManager;
use Tests\TestCase;

/**
 * Verifies which fields the receipt analysis agent is asked to extract.
 */
class TicketAnalyzerPromptTest extends TestCase
{
    /**
     * Build the analyzer with a stubbed agent manager.
     *
     * @return TicketAnalyzer The analyzer under test.
     */
    private function analyzer(): TicketAnalyzer
    {
        return new TicketAnalyzer($this->mock(AiAgentManager::class));
    }

    public function test_title_is_always_requested_even_without_optional_sections(): void
    {
        $prompt = $this->analyzer()->buildUserMessage([
            'place' => false,
            'date' => false,
            'items' => false,
            'taxes' => false,
        ]);

        $this->assertStringContainsString(__('app.economy.ai.title'), $prompt);
        $this->assertStringContainsString(__('app.economy.ai.total'), $prompt);
        $this->assertStringContainsString(__('app.economy.ai.currency'), $prompt);

        // Optional sections stay opt-in.
        $this->assertStringNotContainsString(__('app.economy.ai.items'), $prompt);
        $this->assertStringNotContainsString(__('app.economy.ai.taxes'), $prompt);
    }

    public function test_optional_sections_are_added_when_requested(): void
    {
        $prompt = $this->analyzer()->buildUserMessage([
            'place' => true,
            'date' => true,
            'items' => true,
            'taxes' => true,
        ]);

        foreach (['title', 'total', 'currency', 'place', 'date', 'items', 'taxes'] as $field) {
            $this->assertStringContainsString(__("app.economy.ai.{$field}"), $prompt);
        }
    }
}
