<?php

namespace App\Actions\ShoppingLists;

use App\Data\ShoppingLists\CreateShoppingListData;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a shopping list for a user, optionally scoped to a household.
 */
final class CreateShoppingList
{
    /**
     * @param  CreateShoppingListData  $data  The data for the new list
     * @param  User  $actor  The user creating the list
     * @param  Household|null  $household  The household the list belongs to
     * @return ShoppingList The ShoppingList value.
     */
    public function execute(CreateShoppingListData $data, User $actor, ?Household $household = null): ShoppingList
    {
        $clientId = $this->clientProvidedId();

        return DB::transaction(function () use ($data, $actor, $household, $clientId) {
            $list = new ShoppingList([
                'name' => $data->name,
                'created_by' => $actor->id,
                'household_id' => $household?->id,
            ]);

            $this->assignClientId($list, $clientId);

            try {
                $list->save();
            } catch (UniqueConstraintViolationException) {
                // The pre-check cannot see a row another device committed in
                // between, so the unique index is the referee for that race.
                throw $this->idTaken();
            }

            return $list;
        });
    }

    /**
     * The id the offline client claimed for this write, when it sent one.
     *
     * The API request validates it as a UUID; nothing else feeds ids in, so the
     * action only has to keep it instead of letting HasUuids mint a new one.
     */
    private function clientProvidedId(): ?string
    {
        $request = request();

        if (! $request->routeIs('api.v1.*')) {
            return null;
        }

        $id = $request->input('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Keep the client id, refusing one that a row already owns.
     */
    private function assignClientId(Model $model, ?string $clientId): void
    {
        if ($clientId === null) {
            return;
        }

        // A tombstoned row still occupies its id, so the pre-check has to see it.
        if ($model::withTrashed()->whereKey($clientId)->exists()) {
            throw $this->idTaken();
        }

        $model->id = $clientId;
    }

    private function idTaken(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => 'This id is already used by another record.',
            'code' => 'id_taken',
            'errors' => ['id' => ['This id is already used by another record.']],
        ], 422));
    }
}
