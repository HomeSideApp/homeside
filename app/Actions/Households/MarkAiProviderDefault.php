<?php

namespace App\Actions\Households;

use HomeSide\AiAgents\Models\AiProvider;

/**
 * Marks an AI provider as the default one for its scope.
 */
final class MarkAiProviderDefault
{
    /**
     * @param  AiProvider  $provider  The provider to mark as default
     */
    public function execute(AiProvider $provider): void
    {
        $provider->markAsDefault();
    }
}
