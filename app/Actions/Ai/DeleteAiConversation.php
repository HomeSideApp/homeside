<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use HomeSide\AiAgents\Models\AiActionProposal;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Support\Facades\DB;

/**
 * Deletes an AI conversation along with its runs and action proposals.
 */
class DeleteAiConversation
{
    /**
     * @param  AiConversation  $conversation  The conversation to delete
     */
    public function execute(AiConversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->runs()->delete();
            AiActionProposal::query()
                ->where('conversation_id', $conversation->id)
                ->delete();
            $conversation->delete();
        });
    }
}
