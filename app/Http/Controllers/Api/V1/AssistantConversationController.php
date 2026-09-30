<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\CreateAiConversation;
use App\Actions\Ai\DeleteAiConversation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistant\AiConversationResource;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AssistantConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
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

    public function store(Request $request, AgentRegistry $registry, CreateAiConversation $action): JsonResponse
    {
        $aliases = ['assistant' => 'assistant.homeside', 'recipes' => 'recipes.recipe_generator', 'economy' => 'economy.ticket_analyzer'];
        $validated = $request->validate(['agent' => ['required', Rule::in([...array_keys($aliases), ...$registry->all()])]]);
        $user = $this->authenticatedUser($request);
        $conversation = $action->execute($user, $aliases[$validated['agent']] ?? $validated['agent'], $user->active_household_id);

        return AiConversationResource::make($conversation->loadCount('runs'))->response()->setStatusCode(201);
    }

    public function show(Request $request, AiConversation $conversation): AiConversationResource
    {
        $this->authorize('view', $conversation);

        return new AiConversationResource($conversation->loadCount('runs'));
    }

    public function destroy(AiConversation $conversation, DeleteAiConversation $action): Response
    {
        $this->authorize('delete', $conversation);
        $action->execute($conversation);

        return response()->noContent();
    }
}
