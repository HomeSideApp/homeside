<?php

namespace App\Jobs;

use App\Actions\Ai\SendAiConversationMessage;
use App\Models\User;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RunAssistantMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public string $runId) {}

    public function handle(SendAiConversationMessage $action): void
    {
        $run = AiRun::query()->findOrFail($this->runId);
        $claimed = AiRun::query()
            ->whereKey($run->id)
            ->where('status', 'queued')
            ->update(['status' => 'running']);

        if ($claimed !== 1) {
            return;
        }

        $run->refresh();
        $user = User::query()->findOrFail($run->user_id);
        $conversation = AiConversation::query()->findOrFail($run->conversation_id);

        try {
            $action->execute($user, $conversation, (string) $run->user_message, $run);
            $conversation->touch();
        } catch (Throwable) {
            $run->refresh();
            if ($run->status !== 'cancelled') {
                $run->update([
                    'status' => 'error',
                    'error_code' => 'assistant_execution_failed',
                ]);
            }
        }
    }
}
