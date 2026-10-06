<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAiMessagesRequest;
use App\Http\Requests\SendAiMessageRequest;
use App\Http\Resources\Assistant\AiRunResource;
use App\Jobs\RunAssistantMessageJob;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Execution\ExecutionRecorder;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AssistantMessageController extends Controller
{
    public function index(IndexAiMessagesRequest $request, AiConversation $conversation): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return AiRunResource::collection($conversation->runs()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 50)
            ->withQueryString());
    }

    public function store(SendAiMessageRequest $request, AiConversation $conversation, ExecutionRecorder $recorder): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $run = $recorder->queueRun(new AiExecutionContextData(
            userId: $user->id,
            tenantId: $conversation->household_id,
            conversationId: $conversation->id,
        ), $conversation->agent, $validated['message']);
        $conversation->touch();
        RunAssistantMessageJob::dispatch($run->id);

        return response()->json(['data' => [
            'message' => ['id' => $run->id, 'role' => 'user', 'content' => $validated['message']],
            'run' => (new AiRunResource($run))->resolve($request),
        ]], 202);
    }
}
