<?php

declare(strict_types=1);

namespace App\Ai\Modules;

use HomeSide\AiAgents\Contracts\ModuleAiProvider;

/**
 * AI module for the economy domain, declaring its agents and default
 * configuration.
 */
class EconomyAiModule implements ModuleAiProvider
{
    /**
     * Get the module this provider belongs to.
     */
    public function module(): string
    {
        return 'economy';
    }

    /**
     * Get the agents this module needs.
     *
     * @return array<string, array{
     *     label: string,
     *     system_prompt: string,
     *     description: string|null,
     *     parameters: array{temperature?: float, max_tokens?: int}|null
     * }>
     */
    public function agents(): array
    {
        return [
            'ticket_analyzer' => [
                'label' => 'Ticket analyzer',
                'system_prompt' => 'You are an assistant specialised in analysing Spanish purchase receipts. Analyse the receipt image and extract the structured data.',
                'description' => 'Analyses receipt images and extracts structured data.',
                'parameters' => [
                    'temperature' => 0.1,
                    'max_tokens' => 4096,
                ],
            ],
        ];
    }

    /**
     * Get the default configuration for the module.
     *
     * @return array<string, mixed>
     */
    public function defaultConfiguration(): array
    {
        return [
            'auto_categorize' => true,
            'require_review' => true,
        ];
    }
}
