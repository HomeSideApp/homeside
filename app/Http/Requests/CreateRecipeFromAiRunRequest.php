<?php

namespace App\Http\Requests;

use App\Ai\Execution\RecipeNormalizer;
use HomeSide\AiAgents\Models\AiConversation;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Support\Facades\Validator;

class CreateRecipeFromAiRunRequest extends ViewAiConversationRequest
{
    /** @var array<string, mixed> */
    private array $recipeData = [];

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        /** @var AiRun $run */
        $run = $this->route('run');
        /** @var AiConversation $conversation */
        $conversation = $this->route('conversation');
        abort_unless($run->conversation_id === $conversation->id, 404);

        return true;
    }

    protected function passedValidation(): void
    {
        /** @var AiRun $run */
        $run = $this->route('run');
        abort_unless(in_array($run->status, ['ok', 'success'], true), 409, __('app.errors.recipe_run_not_successful'));
        $decoded = json_decode((string) $run->reply, true);
        abort_unless(is_array($decoded), 409, __('app.errors.recipe_run_without_draft'));
        $draft = RecipeNormalizer::normalizeRecipe($decoded);
        $this->recipeData = Validator::make($draft, (new StoreRecipeApiRequest)->rules())->validate();
    }

    /** @return array<string, mixed> */
    public function recipeData(): array
    {
        return $this->recipeData;
    }
}
