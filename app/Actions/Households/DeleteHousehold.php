<?php

namespace App\Actions\Households;

use App\Models\Household;

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
        $household->delete();
    }
}
