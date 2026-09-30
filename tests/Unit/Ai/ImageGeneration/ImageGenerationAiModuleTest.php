<?php

declare(strict_types=1);

namespace Tests\Unit\Ai\ImageGeneration;

use App\Ai\Modules\ImageGenerationAiModule;
use App\Enums\AiProviderModule;
use HomeSide\AiAgents\Contracts\ModuleAiProvider;
use Tests\TestCase;

class ImageGenerationAiModuleTest extends TestCase
{
    public function test_module_returns_image_generation_enum(): void
    {
        $module = new ImageGenerationAiModule;

        $this->assertSame(AiProviderModule::ImageGeneration->value, $module->module());
    }

    public function test_module_implements_interface(): void
    {
        $module = new ImageGenerationAiModule;

        $this->assertInstanceOf(ModuleAiProvider::class, $module);
    }

    public function test_image_generation_has_no_text_agents(): void
    {
        $module = new ImageGenerationAiModule;
        $agents = $module->agents();

        $this->assertSame([], $agents);
    }

    public function test_module_default_configuration_contains_required_keys(): void
    {
        $module = new ImageGenerationAiModule;
        $config = $module->defaultConfiguration();

        $this->assertArrayHasKey('default_size', $config);
        $this->assertArrayHasKey('default_count', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertSame('1024x1024', $config['default_size']);
    }
}
