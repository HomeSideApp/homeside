<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Recipe;
use App\Models\User;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read-only tool that retrieves the full detail of a recipe by its id.
 *
 * Authorization mirrors RecipePolicy::view: the recipe must either belong to
 * the current user or be shared with a household the user still belongs to.
 * The model only provides the recipe id; ownership checks stay server-side.
 */
class GetRecipeTool implements Tool
{
    /**
     * Create the tool bound to the current execution context.
     *
     * @param  AiExecutionContextData  $context  The execution context carrying the authenticated user id.
     */
    public function __construct(
        private readonly AiExecutionContextData $context,
    ) {}

    /**
     * Human-readable description exposed to the model.
     *
     * @return string The tool description.
     */
    public function description(): string
    {
        return __('app.ai_tools.recipe.description');
    }

    /**
     * JSON schema describing the tool input.
     *
     * @param  JsonSchema  $schema  The schema factory provided by the SDK.
     * @return array<string, mixed> The schema definition array.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recipe_id' => $schema->string()->description(__('app.ai_tools.recipe.recipe_id')),
        ];
    }

    /**
     * Execute the tool and return the accessible recipe as JSON.
     *
     * @param  Request  $request  The tool invocation request from the model.
     * @return string A JSON-encoded recipe with its nested relations.
     *
     * @throws ModelNotFoundException When the recipe does not exist or is not visible to the user.
     */
    public function handle(Request $request): string
    {
        $recipeId = $request->string('recipe_id');
        $user = User::query()->findOrFail($this->context->userId);

        $recipe = Recipe::query()
            ->visibleTo($user)
            ->findOrFail($recipeId);

        return $recipe->load(['sections.ingredients', 'sections.steps', 'cookware', 'tags'])->toJson();
    }
}
