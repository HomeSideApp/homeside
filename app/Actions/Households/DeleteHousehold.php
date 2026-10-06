<?php

namespace App\Actions\Households;

use App\Models\Household;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a household.
 */
final class DeleteHousehold
{
    /**
     * @param  Household  $household  The household to delete
     */
    public function execute(Household $household): void
    {
        DB::transaction(function () use ($household): void {
            $locked = Household::query()->lockForUpdate()->findOrFail($household->id);
            abort_unless(auth()->id() === $locked->owner_id, 403);
            $locked->delete();
        }, 3);
    }
}
