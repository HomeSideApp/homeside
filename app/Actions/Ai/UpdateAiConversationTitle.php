<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Support\Str;

/**
 * Sets a conversation title from the first user message when the
 * conversation has no title yet.
 */
class UpdateAiConversationTitle
{
    /**
     * @param  AiConversation  $conversation  The conversation to update
     * @param  string  $message  The message used to derive the title
     */
    public function execute(AiConversation $conversation, string $message): void
    {
        if (filled($conversation->title)) {
            $conversation->touch();

            return;
        }

        $title = Str::of($message)
            ->replaceMatches('/^Modifica la receta:\s*/ui', '')
            ->squish()
            ->limit(70)
            ->toString();

        $conversation->update([
            'title' => $title !== '' ? $title : 'Nueva conversación',
        ]);
    }
}
