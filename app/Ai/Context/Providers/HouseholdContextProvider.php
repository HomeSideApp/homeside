<?php

declare(strict_types=1);

namespace App\Ai\Context\Providers;

use App\Models\Household;
use HomeSide\AiAgents\Context\ContextProvider;
use HomeSide\AiAgents\Execution\AiExecutionContextData;

/**
 * Context Provider: metadata del hogar (sin datos masivos).
 */
class HouseholdContextProvider implements ContextProvider
{
    public function key(): string
    {
        return 'household';
    }

    public function provide(AiExecutionContextData $context): array
    {
        if ($context->tenantId === null) {
            return [
                'key' => 'household',
                'data' => null,
            ];
        }

        $household = Household::find($context->tenantId);

        return [
            'key' => 'household',
            'data' => $household !== null ? [
                'id' => $household->id,
                'name' => $household->name,
            ] : null,
        ];
    }
}
