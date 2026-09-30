<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ShoppingList;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read-only tool that retrieves shopping lists accessible to the current user.
 *
 * Authorization is resolved from the user's CURRENT memberships: lists owned by
 * any household the user belongs to, plus the user's own personal lists. It
 * never trusts a household id stored on the conversation, which closes the
 * cross-household IDOR identified in the security review.
 *
 * Limits: default 5, maximum 10.
 */
class GetShoppingListsTool implements Tool
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

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
        return __('app.ai_tools.shopping_lists.description');
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
            'limit' => $schema->integer()->nullable()->description(__('app.ai_tools.shopping_lists.limit')),
        ];
    }

    /**
     * Execute the tool and return the accessible shopping lists as JSON.
     *
     * @param  Request  $request  The tool invocation request from the model.
     * @return string A JSON-encoded list of accessible shopping lists.
     */
    public function handle(Request $request): string
    {
        $limit = min(
            $request->integer('limit', self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $userId = $this->context->userId;

        return ShoppingList::query()
            ->where(function (Builder $query) use ($userId): void {
                $query->where('created_by', $userId)
                    ->orWhereHas('household.members', function (Builder $memberQuery) use ($userId): void {
                        $memberQuery->where('user_id', $userId);
                    });
            })
            ->limit($limit)
            ->get(['id', 'name', 'household_id'])
            ->toJson();
    }
}
