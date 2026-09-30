<?php

namespace App\Actions\Households;

use HomeSide\AiAgents\Models\AiProvider;

/**
 * Loads an AI provider with its household and creator relations.
 */
final class GetAiProvider
{
    /**
     * @param  AiProvider  $provider  The provider to load
     * @return AiProvider The AiProvider value.
     */
    public function execute(AiProvider $provider): AiProvider
    {
        return $provider->load(['household', 'creator']);
    }
}
