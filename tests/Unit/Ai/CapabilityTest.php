<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Configuration\Capability;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CapabilityTest extends TestCase
{
    #[Test]
    public function all_capabilities_have_unique_values(): void
    {
        $values = array_map(fn (Capability $c) => $c->value, Capability::cases());

        $this->assertSame($values, array_unique($values));
    }

    #[Test]
    public function driver_baseline_returns_capabilities_for_openai(): void
    {
        $capabilities = Capability::driverBaseline('openai');

        $this->assertContains(Capability::Text, $capabilities);
        $this->assertContains(Capability::StructuredOutput, $capabilities);
        $this->assertContains(Capability::Tools, $capabilities);
        $this->assertContains(Capability::Streaming, $capabilities);
    }

    #[Test]
    public function driver_baseline_returns_capabilities_for_ollama(): void
    {
        $capabilities = Capability::driverBaseline('ollama');

        $this->assertContains(Capability::Text, $capabilities);
        $this->assertContains(Capability::StructuredOutput, $capabilities);
        $this->assertContains(Capability::Tools, $capabilities);
    }

    #[Test]
    public function driver_baseline_returns_only_text_for_unknown_driver(): void
    {
        $capabilities = Capability::driverBaseline('unknown-driver');

        $this->assertCount(1, $capabilities);
        $this->assertContains(Capability::Text, $capabilities);
    }

    #[Test]
    public function openai_compatible_returns_text_and_streaming(): void
    {
        $capabilities = Capability::driverBaseline('openai-compatible');

        $this->assertContains(Capability::Text, $capabilities);
        $this->assertContains(Capability::Streaming, $capabilities);
        $this->assertNotContains(Capability::StructuredOutput, $capabilities);
    }

    #[Test]
    public function from_value_resolves_correctly(): void
    {
        $this->assertSame(Capability::Text, Capability::from('text'));
        $this->assertSame(Capability::Vision, Capability::from('vision'));
        $this->assertSame(Capability::Tools, Capability::from('tools'));
    }
}
