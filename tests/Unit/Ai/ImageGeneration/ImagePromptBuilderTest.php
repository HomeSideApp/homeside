<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\ImageGeneration;

use App\Ai\Prompting\ImagePromptBuilder;
use Tests\TestCase;

class ImagePromptBuilderTest extends TestCase
{
    public function test_for_product_icon_generates_monochromatic_prompt(): void
    {
        $prompt = ImagePromptBuilder::forProductIcon('milk');

        $this->assertStringContainsString('milk', $prompt);
        $this->assertStringContainsString('Flat minimalist icon', $prompt);
        $this->assertStringContainsString('black outline with white interior', $prompt);
        $this->assertStringContainsString('transparent background', $prompt);
        $this->assertStringContainsString('NOT a solid silhouette', $prompt);
    }

    public function test_for_product_icon_includes_category_when_provided(): void
    {
        $prompt = ImagePromptBuilder::forProductIcon('milk', 'Bebidas');

        $this->assertStringContainsString('category: Bebidas', $prompt);
    }

    public function test_for_product_icon_omits_category_when_null(): void
    {
        $prompt = ImagePromptBuilder::forProductIcon('milk', null);

        $this->assertStringNotContainsString('category:', $prompt);
    }

    public function test_for_free_image_returns_description_as_is(): void
    {
        $description = 'A sunset over the ocean with warm colors';
        $prompt = ImagePromptBuilder::forFreeImage($description);

        $this->assertSame($description, $prompt);
    }

    public function test_for_product_icon_does_not_contain_color(): void
    {
        $prompt = ImagePromptBuilder::forProductIcon('bread');

        $this->assertStringContainsString('no color', $prompt);
        $this->assertStringContainsString('no gradients', $prompt);
        $this->assertStringContainsString('no shadows', $prompt);
        $this->assertStringContainsString('white interior', $prompt);
    }
}
