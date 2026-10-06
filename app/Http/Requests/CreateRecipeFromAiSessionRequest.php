<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;

class CreateRecipeFromAiSessionRequest extends ViewAiConversationRequest
{
    /** @var array<string, mixed> */
    private array $recipeData = [];

    protected function passedValidation(): void
    {
        /** @var AiConversation $conversation */
        $conversation = $this->route('conversation');
        $recipeRun = $this->route('recipeRun');
        $currentRecipeKey = $this->session()->get('ai_recipe_current_'.$conversation->id);
        $draft = $this->session()->get('ai_recipe_'.$recipeRun);
        if ($currentRecipeKey !== $recipeRun || ! is_array($draft)) {
            throw new HttpResponseException(response()->json([
                'message' => __('app.errors.last_proposal_not_recipe'),
            ], 409));
        }
        $this->recipeData = Validator::make($draft, (new StoreRecipeRequest)->rules())->validate();
    }

    /** @return array<string, mixed> */
    public function recipeData(): array
    {
        return $this->recipeData;
    }
}
