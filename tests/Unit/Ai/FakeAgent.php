<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Contracts\HomeSideAgent;
use HomeSide\AiAgents\Configuration\Capability;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Agent falso para tests unitarios del AgentRegistry.
 */
class FakeAgent implements Agent, HomeSideAgent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a fake agent for testing.';
    }

    public function key(): string
    {
        return 'test.fake_agent';
    }

    public function module(): string
    {
        return 'test';
    }

    public function version(): int
    {
        return 1;
    }

    public function requiredCapabilities(): array
    {
        return [Capability::Text];
    }

    public function defaultConfiguration(): array
    {
        return [
            'temperature' => 0.5,
            'max_tokens' => 1024,
        ];
    }

    public function contextProviders(): array
    {
        return [];
    }

    public function maxContextTokens(): int
    {
        return 4096;
    }
}
