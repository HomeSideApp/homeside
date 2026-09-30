<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Product;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read-only tool that searches the shared product catalog for ingredients.
 *
 * The product catalog is global by design (unique slug, no household
 * ownership), matching the read path used by AssistantController and
 * RecipeAiController. The model only provides the query and limit.
 *
 * Limits: default 10, maximum 50.
 */
class GetAvailableIngredientsTool implements Tool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

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
        return __('app.ai_tools.available_ingredients.description');
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
            'query' => $schema->string()->description(__('app.ai_tools.available_ingredients.query')),
            'limit' => $schema->integer()->nullable()->description(__('app.ai_tools.available_ingredients.limit')),
        ];
    }

    /**
     * Execute the tool and return matching active catalog products as JSON.
     *
     * @param  Request  $request  The tool invocation request from the model.
     * @return string A JSON-encoded list of matching products.
     */
    public function handle(Request $request): string
    {
        $query = $request->string('query', '');
        $limit = min(
            $request->integer('limit', self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $products = Product::where('is_active', true)
            ->when($query->isNotEmpty(), fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'category_id']);

        return $products->toJson();
    }
}
