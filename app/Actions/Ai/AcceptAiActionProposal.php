<?php

declare(strict_types=1);

namespace App\Actions\Ai;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\ShoppingLists\AddListItem;
use App\Data\Recipes\CreateRecipeData;
use App\Http\Requests\StoreRecipeApiRequest;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Accepts an AI action proposal and delegates to the corresponding
 * domain action.
 *
 * The assistant never mutates data directly; it generates proposals that
 * the user accepts. This action delegates to the existing domain actions.
 */
class AcceptAiActionProposal
{
    public function __construct(
        private readonly AddListItem $addListItem,
        private readonly CreateRecipe $createRecipe,
    ) {}

    /**
     * @param  User  $user  The user accepting the proposal
     * @param  AiActionProposal  $proposal  The proposal to accept
     * @return array<string, mixed>
     */
    public function execute(User $user, AiActionProposal $proposal): array
    {
        abort_unless($proposal->user_id === $user->id, 404);
        abort_if($proposal->expires_at?->isPast(), 409, 'The proposal has expired.');
        abort_unless($proposal->isPending(), 409, 'Only pending proposals can be accepted.');

        return DB::transaction(function () use ($proposal, $user): array {
            $result = match ($proposal->type) {
                'add_shopping_items' => $this->addShoppingItems($user, $proposal),
                'create_recipe' => $this->createRecipeFromProposal($user, $proposal),
                default => abort(409, "Unsupported proposal type: {$proposal->type}"),
            };
            $proposal->accept();

            return $result;
        });
    }

    /**
     * Execute an "add shopping items" proposal.
     *
     * @return array{status: string, type: string, message: string}
     */
    private function addShoppingItems(User $user, AiActionProposal $proposal): array
    {
        $payload = Validator::make($proposal->payload, [
            'list_id' => ['required', 'uuid', 'exists:shopping_lists,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.custom_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['sometimes', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $list = ShoppingList::query()->findOrFail($payload['list_id']);
        Gate::forUser($user)->authorize('update', $list);
        abort_unless($proposal->household_id === null || $list->household_id === $proposal->household_id, 404);
        $items = collect($payload['items'])
            ->map(fn (array $item) => $this->addListItem->execute($list, $item, $user->id))
            ->map->toArray()
            ->all();

        return [
            'status' => 'executed',
            'type' => $proposal->type,
            'message' => 'Propuesta ejecutada exitosamente.',
            'resource' => ['list_id' => $list->id, 'items' => $items],
        ];
    }

    /**
     * Execute a "create recipe" proposal.
     *
     * @return array{status: string, type: string, message: string}
     */
    private function createRecipeFromProposal(User $user, AiActionProposal $proposal): array
    {
        Gate::forUser($user)->authorize('create', Recipe::class);
        $payload = $proposal->payload['recipe'] ?? $proposal->payload;
        $validated = Validator::make($payload, (new StoreRecipeApiRequest)->rules())->validate();
        $recipe = $this->createRecipe->execute(CreateRecipeData::fromArray($validated), $user);

        return [
            'status' => 'executed',
            'type' => $proposal->type,
            'message' => 'Propuesta ejecutada exitosamente.',
            'resource' => ['recipe_id' => $recipe->id, 'name' => $recipe->name],
        ];
    }
}
