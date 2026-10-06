<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\CreateRecipe;
use App\Data\Recipes\CreateRecipeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateRecipeFromAiRunRequest;
use App\Http\Requests\ViewAiRunRequest;
use App\Http\Resources\Assistant\AiRunResource;
use App\Http\Resources\Recipes\RecipeResource;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\JsonResponse;

final class AssistantRunController extends Controller
{
    public function show(ViewAiRunRequest $request, AiRun $run): AiRunResource
    {

        return new AiRunResource($run);
    }

    public function cancel(ViewAiRunRequest $request, AiRun $run): AiRunResource
    {
        if (in_array($run->status, ['queued', 'running'], true)) {
            $run->update(['status' => 'cancelled']);
        }

        return new AiRunResource($run->fresh());
    }

    public function storeRecipe(
        CreateRecipeFromAiRunRequest $request,
        AiConversation $conversation,
        AiRun $run,
        CreateRecipe $action,
    ): JsonResponse {
        $validated = $request->recipeData();
        $recipe = $action->execute(CreateRecipeData::fromArray($validated), $this->authenticatedUser($request));
        $run->update(['metadata' => [...($run->metadata ?? []), 'recipe_key' => $recipe->id, 'result' => ['recipe_id' => $recipe->id]]]);

        return response()->json(['data' => [
            'recipe' => (new RecipeResource($recipe))->resolve($request),
            'message' => 'Recipe created successfully.',
        ]], 201);
    }
}
