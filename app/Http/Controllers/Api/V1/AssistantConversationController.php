<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\CreateAiConversation;
use App\Actions\Ai\DeleteAiConversation;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteAiConversationRequest;
use App\Http\Requests\IndexAiConversationsRequest;
use App\Http\Requests\StoreAiConversationRequest;
use App\Http\Requests\ViewAiConversationRequest;
use App\Http\Resources\Assistant\AiConversationResource;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AssistantConversationController extends Controller
{
    public function index(IndexAiConversationsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $conversations = AiConversation::query()
            ->forUser($user->id)
            ->where(function ($query) use ($user): void {
                $query->whereNull('household_id')
                    ->orWhereIn('household_id', $user->households()->select('households.id'));
            })
            ->withCount('runs')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return AiConversationResource::collection($conversations);
    }

    public function store(StoreAiConversationRequest $request, AgentRegistry $registry, CreateAiConversation $action): JsonResponse
    {
        $aliases = StoreAiConversationRequest::AGENT_ALIASES;
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $conversation = $action->execute($user, $aliases[$validated['agent']] ?? $validated['agent'], $user->active_household_id);

        return AiConversationResource::make($conversation->loadCount('runs'))->response()->setStatusCode(201);
    }

    public function show(ViewAiConversationRequest $request, AiConversation $conversation): AiConversationResource
    {

        return new AiConversationResource($conversation->loadCount('runs'));
    }

    public function destroy(DeleteAiConversationRequest $request, AiConversation $conversation, DeleteAiConversation $action): Response
    {
        $action->execute($conversation);

        return response()->noContent();
    }
}
