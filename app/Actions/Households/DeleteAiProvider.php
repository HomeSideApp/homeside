<?php

namespace App\Actions\Households;

use HomeSide\AiAgents\Models\AiProvider;

/**
 * Deletes a household AI provider.
 */
final class DeleteAiProvider
{
    /**
     * @param  AiProvider  $provider  The provider to delete
     */
    public function execute(AiProvider $provider): void
    {
        $provider->delete();
    }
}
