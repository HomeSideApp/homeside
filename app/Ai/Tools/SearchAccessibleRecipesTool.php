<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Recipe;
use App\Models\User;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read-only tool that searches the recipes visible to the current user.
 *
 * Authorization mirrors RecipePolicy::view: recipes owned by the user plus
 * recipes shared with any household the user still belongs to, so the
 * assistant can interact with the full history across every one of the
 * user's households. The model only provides the query and limit.
 *
 * Limits: default 5, maximum 20.
 */
class SearchAccessibleRecipesTool implements Tool
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 20;

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
        return __('app.ai_tools.search_recipes.description');
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
            'query' => $schema->string()->description(__('app.ai_tools.search_recipes.query')),
            'limit' => $schema->integer()->nullable()->description(__('app.ai_tools.search_recipes.limit')),
        ];
    }

    /**
     * Execute the tool and return matching visible recipes as JSON.
     *
     * @param  Request  $request  The tool invocation request from the model.
     * @return string A JSON-encoded list of matching recipes.
     */
    public function handle(Request $request): string
    {
        $query = $request->string('query', '');
        $limit = min(
            $request->integer('limit', self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $user = User::query()->findOrFail($this->context->userId);

        $recipes = Recipe::query()
            ->visibleTo($user)
            ->when($query->isNotEmpty(), fn ($q) => $q->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            }))
            ->limit($limit)
            ->get(['id', 'name', 'description', 'servings', 'prep_time_seconds', 'cook_time_seconds', 'difficulty', 'cuisine']);

        return $recipes->toJson();
    }
}
