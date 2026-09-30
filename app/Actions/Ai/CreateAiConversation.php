<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Models\User;
use HomeSide\AiAgents\Models\AiConversation;

/**
 * Creates a new AI conversation for a user and agent.
 */
class CreateAiConversation
{
    /**
     * @param  User  $user  The user owning the conversation
     * @param  string  $agent  The agent key the conversation is for
     * @param  string|null  $householdId  The household scope, falling back to the user's active household
     * @param  string|null  $title  An optional conversation title
     * @return AiConversation The AiConversation value.
     */
    public function execute(
        User $user,
        string $agent,
        ?string $householdId = null,
        ?string $title = null,
    ): AiConversation {
        return AiConversation::create([
            'user_id' => $user->id,
            'household_id' => $householdId ?? $user->active_household_id,
            'agent' => $agent,
            'title' => $title,
        ]);
    }
}
