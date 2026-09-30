<?php

namespace App\Actions\Households;

use App\Models\Household;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists the AI providers of a household.
 */
final class ListAiProviders
{
    /**
     * @param  Household  $household  The household to list providers for
     * @return Collection<int, AiProvider>
     */
    public function execute(Household $household): Collection
    {
        return $household->aiProviders()->get();
    }
}
