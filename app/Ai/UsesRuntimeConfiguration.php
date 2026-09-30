<?php

declare(strict_types=1);

namespace App\Ai;

trait UsesRuntimeConfiguration
{
    /** @var array<string, int|float> */
    private array $runtimeConfiguration = [];

    /** @param array<string, int|float> $parameters */
    public function setRuntimeConfiguration(array $parameters): void
    {
        $this->runtimeConfiguration = $parameters;
    }

    protected function runtimeMaxTokens(int $default): int
    {
        return (int) ($this->runtimeConfiguration['max_tokens'] ?? $default);
    }

    protected function runtimeTemperature(float $default): float
    {
        return (float) ($this->runtimeConfiguration['temperature'] ?? $default);
    }

    protected function runtimeMaxSteps(int $default): int
    {
        return (int) ($this->runtimeConfiguration['max_steps'] ?? $default);
    }

    public function topP(): float
    {
        return (float) ($this->runtimeConfiguration['top_p'] ?? 1.0);
    }
}
