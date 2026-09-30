<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ListItem;
use App\Models\ShoppingList;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read-only tool that retrieves the items of a specific shopping list.
 *
 * Authorization is resolved from the user's CURRENT memberships: only items of
 * lists created by the user or owned by a household the user still belongs to
 * are returned. A household id stored on the conversation is never trusted.
 *
 * Limits: default 20, maximum 100.
 */
class GetShoppingListItemsTool implements Tool
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

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
        return __('app.ai_tools.shopping_list_items.description');
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
            'list_id' => $schema->string()->description(__('app.ai_tools.shopping_list_items.list_id')),
            'limit' => $schema->integer()->nullable()->description(__('app.ai_tools.shopping_list_items.limit')),
        ];
    }

    /**
     * Execute the tool and return the accessible list items as JSON.
     *
     * @param  Request  $request  The tool invocation request from the model.
     * @return string A JSON-encoded list of items, or an error object when the list is not accessible.
     */
    public function handle(Request $request): string
    {
        $listId = $request->string('list_id');
        $limit = min(
            $request->integer('limit', self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $userId = $this->context->userId;

        $accessibleList = ShoppingList::query()
            ->where('id', $listId)
            ->where(function (Builder $query) use ($userId): void {
                $query->where('created_by', $userId)
                    ->orWhereHas('household.members', function (Builder $memberQuery) use ($userId): void {
                        $memberQuery->where('user_id', $userId);
                    });
            })
            ->first();

        if ($accessibleList === null) {
            return json_encode(['error' => __('app.ai_tools.shopping_list_items.not_accessible')], JSON_THROW_ON_ERROR);
        }

        return ListItem::where('list_id', $accessibleList->id)
            ->limit($limit)
            ->get(['id', 'custom_name', 'product_id', 'quantity', 'unit', 'is_checked'])
            ->toJson();
    }
}
