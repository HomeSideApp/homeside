<?php

declare(strict_types=1);

namespace App\Ai\Context\Providers;

use App\Models\User;
use HomeSide\AiAgents\Context\ContextProvider;
use HomeSide\AiAgents\Execution\AiExecutionContextData;

/**
 * Context Provider: datos del usuario (locale, timezone, preferencias).
 */
class UserContextProvider implements ContextProvider
{
    public function key(): string
    {
        return 'user';
    }

    public function provide(AiExecutionContextData $context): array
    {
        $user = User::find($context->userId);

        return [
            'key' => 'user',
            'data' => [
                'id' => $context->userId,
                'locale' => $context->locale,
                'timezone' => $context->timezone,
                'name' => $user?->name,
            ],
        ];
    }
}
