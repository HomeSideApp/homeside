<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileRecipeReferences as ReconcileRecipeReferencesJob;
use Illuminate\Console\Command;

/**
 * Class ReconcileRecipeReferences
 *
 * This command dispatches the reconciliation of Cooklang links between recipes.
 */
final class ReconcileRecipeReferences extends Command
{
    protected $signature = 'recipes:reconcile-references';

    protected $description = 'Dispatch the reconciliation of Cooklang links between recipes';

    /**
     * Dispatch the reconciliation job.
     *
     * @return int The integer result.
     */
    public function handle(): int
    {
        ReconcileRecipeReferencesJob::dispatch();
        $this->info('Recipe references reconciliation dispatched to the queue.');

        return self::SUCCESS;
    }
}
