<?php

declare(strict_types=1);

namespace App\Ai\Prompting;

/**
 * Builds optimized prompts for image generation via OpenAI-compatible APIs.
 *
 * Provides prompt templates for different use cases: product icons,
 * free-form images, and category-specific variations.
 */
class ImagePromptBuilder
{
    /**
     * Build a prompt for generating a flat minimalist product icon.
     *
     * PNG with transparent background, monochromatic (black on transparent).
     * Matches the Icons8 flat design style used in public/icons/.
     *
     * @param  string  $productName  The product name to generate an icon for
     * @param  string|null  $category  Optional category for style guidance
     */
    public static function forProductIcon(string $productName, ?string $category = null): string
    {
        $categoryHint = $category !== null ? " (category: {$category})" : '';

        return "Flat minimalist icon of {$productName}{$categoryHint}, "
            .'black outline with white interior details on transparent background, '
            .'clean line art style, rounded corners, no gradients, no shadows, '
            .'no color, high contrast, centered composition, '
            .'grocery product icon style, Icons8 flat design aesthetic, '
            .'NOT a solid silhouette, must have visible white spaces inside the shape';
    }

    /**
     * Build a prompt for generating a free-form image from user description.
     *
     * @param  string  $description  The user's image description
     */
    public static function forFreeImage(string $description): string
    {
        return $description;
    }
}
