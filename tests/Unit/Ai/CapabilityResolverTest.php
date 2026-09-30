<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use HomeSide\AiAgents\Configuration\Capability;
use HomeSide\AiAgents\Configuration\CapabilityResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CapabilityResolverTest extends TestCase
{
    private CapabilityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CapabilityResolver;
    }

    #[Test]
    public function resolve_returns_override_when_provided(): void
    {
        $result = $this->resolver->resolve(
            driver: 'openai',
            override: ['text', 'vision'],
        );

        $this->assertCount(2, $result);
        $this->assertContains(Capability::Text, $result);
        $this->assertContains(Capability::Vision, $result);
    }

    #[Test]
    public function resolve_returns_detected_when_no_override(): void
    {
        $result = $this->resolver->resolve(
            driver: 'openai-compatible',
            detected: ['text', 'structured_output', 'tools'],
        );

        $this->assertCount(3, $result);
        $this->assertContains(Capability::StructuredOutput, $result);
    }

    #[Test]
    public function resolve_falls_back_to_driver_baseline(): void
    {
        $result = $this->resolver->resolve(driver: 'openai');

        $this->assertContains(Capability::Text, $result);
        $this->assertContains(Capability::StructuredOutput, $result);
        $this->assertContains(Capability::Tools, $result);
    }

    #[Test]
    public function is_compatible_returns_true_when_all_required_present(): void
    {
        $effective = [Capability::Text, Capability::StructuredOutput, Capability::Tools];
        $required = [Capability::Text, Capability::StructuredOutput];

        $this->assertTrue($this->resolver->isCompatible($effective, $required));
    }

    #[Test]
    public function is_compatible_returns_false_when_missing_required(): void
    {
        $effective = [Capability::Text];
        $required = [Capability::Text, Capability::Vision];

        $this->assertFalse($this->resolver->isCompatible($effective, $required));
    }

    #[Test]
    public function missing_returns_empty_when_all_present(): void
    {
        $effective = [Capability::Text, Capability::Vision];
        $required = [Capability::Text];

        $missing = $this->resolver->missing($effective, $required);

        $this->assertEmpty($missing);
    }

    #[Test]
    public function missing_returns_capabilities_not_in_effective(): void
    {
        $effective = [Capability::Text];
        $required = [Capability::Text, Capability::Vision, Capability::Tools];

        $missing = $this->resolver->missing($effective, $required);

        $this->assertCount(2, $missing);
        $this->assertContains(Capability::Vision, $missing);
        $this->assertContains(Capability::Tools, $missing);
    }

    #[Test]
    public function ensure_compatible_throws_when_incompatible(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no soporta las capabilities requeridas');

        $this->resolver->ensureCompatible(
            effective: [Capability::Text],
            required: [Capability::Text, Capability::Vision],
            agentKey: 'test.agent',
            model: 'gpt-4o',
        );
    }

    #[Test]
    public function ensure_compatible_does_not_throw_when_compatible(): void
    {
        $this->resolver->ensureCompatible(
            effective: [Capability::Text, Capability::StructuredOutput],
            required: [Capability::Text],
            agentKey: 'test.agent',
            model: 'gpt-4o',
        );

        // No exception thrown = test passes
        $this->assertTrue(true);
    }
}
