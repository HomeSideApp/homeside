<?php

declare(strict_types=1);

namespace App\Ai\Modules;

use HomeSide\AiAgents\Contracts\ModuleAiProvider;

/**
 * AI module for image generation and its default configuration.
 *
 * This module manages OpenAI-compatible image generation providers
 * (NaN Builders FLUX, OpenAI DALL-E, Together AI, etc.).
 */
class ImageGenerationAiModule implements ModuleAiProvider
{
    /**
     * Get the module this provider belongs to.
     */
    public function module(): string
    {
        return 'image_generation';
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
        return [];
    }

    /**
     * Get the default configuration for the module.
     *
     * @return array<string, mixed>
     */
    public function defaultConfiguration(): array
    {
        return [
            'default_size' => '1024x1024',
            'default_count' => 3,
            'timeout' => 60,
        ];
    }
}
