<?php

declare(strict_types=1);

namespace App\Ai\Modules;

use App\Ai\Agents\TranslationGeneratorAgent;
use HomeSide\AiAgents\Contracts\ModuleAiProvider;

/**
 * AI module for the translations domain, declaring its agents and default
 * configuration.
 */
class TranslationsAiModule implements ModuleAiProvider
{
    /**
     * Get the module this provider belongs to.
     */
    public function module(): string
    {
        return 'translations';
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
            'generator' => [
                'label' => 'Translation generator',
                'system_prompt' => (new TranslationGeneratorAgent)->instructions(),
                'description' => 'Translates batches of catalog names into the target language and returns the result as structured JSON.',
                'parameters' => [
                    'temperature' => 0.2,
                    'max_tokens' => 2048,
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
        return [];
    }
}
