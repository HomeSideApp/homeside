<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\CreateRecipe;
use App\Ai\Execution\RecipeNormalizer;
use App\Data\Recipes\CreateRecipeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeApiRequest;
use App\Http\Resources\Assistant\AiRunResource;
use App\Http\Resources\Recipes\RecipeResource;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AssistantRunController extends Controller
{
    public function show(Request $request, AiRun $run): AiRunResource
    {
        $this->authorizeRun($request, $run);

        return new AiRunResource($run);
    }

    public function cancel(Request $request, AiRun $run): AiRunResource
    {
        $this->authorizeRun($request, $run);
        if (in_array($run->status, ['queued', 'running'], true)) {
            $run->update(['status' => 'cancelled']);
        }

        return new AiRunResource($run->fresh());
    }

    public function storeRecipe(
        Request $request,
        AiConversation $conversation,
        AiRun $run,
        CreateRecipe $action,
    ): JsonResponse {
        $this->authorize('view', $conversation);
        abort_unless($run->conversation_id === $conversation->id, 404);
        abort_unless(in_array($run->status, ['ok', 'success'], true), 409, 'The run has no successful recipe result.');
        $decoded = json_decode((string) $run->reply, true);
        abort_unless(is_array($decoded), 409, 'The run does not contain a recipe draft.');
        $draft = RecipeNormalizer::normalizeRecipe($decoded);
        $validated = Validator::make($draft, (new StoreRecipeApiRequest)->rules())->validate();
        $recipe = $action->execute(CreateRecipeData::fromArray($validated), $this->authenticatedUser($request));
        $run->update(['metadata' => [...($run->metadata ?? []), 'recipe_key' => $recipe->id, 'result' => ['recipe_id' => $recipe->id]]]);

        return response()->json(['data' => [
            'recipe' => (new RecipeResource($recipe))->resolve($request),
            'message' => 'Recipe created successfully.',
        ]], 201);
    }

    private function authorizeRun(Request $request, AiRun $run): void
    {
        abort_unless($run->user_id === $this->authenticatedUser($request)->id, 404);
        if ($run->conversation !== null) {
            $this->authorize('view', $run->conversation);
        }
    }
}
