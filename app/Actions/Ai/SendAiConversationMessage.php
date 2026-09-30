<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Models\User;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Execution\AiExecutionResultData;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Support\Facades\Gate;

/**
 * Sends a user message to the agent backing a conversation.
 */
class SendAiConversationMessage
{
    public function __construct(
        private readonly AiAgentManager $manager,
    ) {}

    /**
     * @param  User  $user  The user sending the message
     * @param  AiConversation  $conversation  The conversation context
     * @param  string  $message  The message text
     * @return AiExecutionResultData The AiExecutionResultData value.
     */
    public function execute(
        User $user,
        AiConversation $conversation,
        string $message,
        ?AiRun $run = null,
    ): AiExecutionResultData {
        Gate::forUser($user)->authorize('view', $conversation);

        if ($run !== null && ($run->user_id !== $user->id || $run->conversation_id !== $conversation->id)) {
            throw new \RuntimeException('The run does not belong to this conversation.');
        }

        $context = new AiExecutionContextData(
            userId: $user->id,
            tenantId: $conversation->household_id,
            conversationId: $conversation->id,
            locale: $user->preferredLocale(),
            timezone: 'Europe/Madrid',
        );

        return $this->manager->run(
            agentKey: $conversation->agent,
            context: $context,
            userMessage: $message,
            existingRun: $run,
        );
    }
}
