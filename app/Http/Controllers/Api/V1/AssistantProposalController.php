<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\AcceptAiActionProposal;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexAiProposalsRequest;
use App\Http\Requests\RejectAiProposalRequest;
use App\Http\Requests\ViewAiProposalRequest;
use App\Http\Resources\Assistant\AiProposalResource;
use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AssistantProposalController extends Controller
{
    public function index(IndexAiProposalsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = AiActionProposal::query()
            ->forUser($this->authenticatedUser($request)->id)
            ->when(($validated['status'] ?? null) === 'expired', fn ($query) => $query->where('status', 'pending')->where('expires_at', '<=', now()))
            ->when(isset($validated['status']) && $validated['status'] !== 'expired', fn ($query) => $query->where('status', $validated['status']))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return AiProposalResource::collection($query->paginate($validated['perPage'] ?? 15)->withQueryString());
    }

    public function show(ViewAiProposalRequest $request, AiActionProposal $proposal): AiProposalResource
    {

        return new AiProposalResource($proposal);
    }

    public function accept(ViewAiProposalRequest $request, AiActionProposal $proposal, AcceptAiActionProposal $action): JsonResponse
    {
        $result = $action->execute($this->authenticatedUser($request), $proposal);

        return response()->json(['data' => [
            'proposal' => (new AiProposalResource($proposal->fresh()))->resolve($request),
            'result' => $result['resource'] ?? null,
            'message' => $result['message'],
        ]]);
    }

    public function reject(RejectAiProposalRequest $request, AiActionProposal $proposal): JsonResponse
    {
        $proposal->reject();

        return response()->json(['data' => ['proposal' => (new AiProposalResource($proposal->fresh()))->resolve($request)]]);
    }
}
