<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Context\ContextProvider;
use HomeSide\AiAgents\Execution\AiExecutionContextData;

/**
 * ContextProvider falso para tests.
 */
final class FakeContextProvider implements ContextProvider
{
    public function __construct(
        private readonly string $providerKey = 'test_context',
        private readonly mixed $data = ['test' => true],
    ) {}

    public function key(): string
    {
        return $this->providerKey;
    }

    public function provide(AiExecutionContextData $context): array
    {
        return [
            'key' => $this->providerKey,
            'data' => $this->data,
        ];
    }
}
