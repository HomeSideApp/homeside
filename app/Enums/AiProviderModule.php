<?php

namespace App\Enums;

/**
 * Modules that can own an AI provider.
 */
enum AiProviderModule: string
{
    case Economy = 'economy';
    case Recipes = 'recipes';
    case Assistant = 'assistant';
    case General = 'general';
    case ImageGeneration = 'image_generation';
    case Translations = 'translations';

    /**
     * Get the human-readable label for the module.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::Economy => 'Economy (receipt analysis)',
            self::Recipes => 'Recipes',
            self::Assistant => 'Assistant',
            self::General => 'General usage',
            self::ImageGeneration => 'Image generation',
            self::Translations => 'Translations',
        };
    }
}
