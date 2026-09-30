<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\AcceptAiActionProposal;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistant\AiProposalResource;
use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class AssistantProposalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'accepted', 'rejected', 'expired'])],
            'type' => ['sometimes', Rule::in(['add_shopping_items', 'create_recipe'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = AiActionProposal::query()
            ->forUser($this->authenticatedUser($request)->id)
            ->when(($validated['status'] ?? null) === 'expired', fn ($query) => $query->where('status', 'pending')->where('expires_at', '<=', now()))
            ->when(isset($validated['status']) && $validated['status'] !== 'expired', fn ($query) => $query->where('status', $validated['status']))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return AiProposalResource::collection($query->paginate($validated['perPage'] ?? 15)->withQueryString());
    }

    public function show(Request $request, AiActionProposal $proposal): AiProposalResource
    {
        $this->ensureOwner($request, $proposal);

        return new AiProposalResource($proposal);
    }

    public function accept(Request $request, AiActionProposal $proposal, AcceptAiActionProposal $action): JsonResponse
    {
        $this->ensureOwner($request, $proposal);
        $result = $action->execute($this->authenticatedUser($request), $proposal);

        return response()->json(['data' => [
            'proposal' => (new AiProposalResource($proposal->fresh()))->resolve($request),
            'result' => $result['resource'] ?? null,
            'message' => $result['message'],
        ]]);
    }

    public function reject(Request $request, AiActionProposal $proposal): JsonResponse
    {
        $this->ensureOwner($request, $proposal);
        abort_if($proposal->expires_at?->isPast(), 409, 'The proposal has expired.');
        abort_unless($proposal->isPending(), 409, 'Only pending proposals can be rejected.');
        $proposal->reject();

        return response()->json(['data' => ['proposal' => (new AiProposalResource($proposal->fresh()))->resolve($request)]]);
    }

    private function ensureOwner(Request $request, AiActionProposal $proposal): void
    {
        abort_unless($proposal->user_id === $this->authenticatedUser($request)->id, 404);
    }
}
