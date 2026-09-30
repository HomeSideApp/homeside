<?php

namespace App\Console\Commands;

use App\Enums\EconomicImportStatus;
use App\Models\EconomicImport;
use Illuminate\Console\Command;

/**
 * Marks abandoned economic imports as failed so the import list never shows an endless analysis.
 */
final class PruneStaleEconomicImports extends Command
{
    protected $signature = 'economy:prune-imports
        {--hours=24 : The age in hours after which a pending import is considered stale}';

    protected $description = 'Mark economic imports stuck in pending or processing as failed';

    /**
     * Mark every abandoned import as failed with a stale error code.
     *
     * A job that never ran, or a queue that was flushed, would otherwise leave an import in
     * `pending` forever, blocking the user from either retrying or discarding it.
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        $hours = max((int) $this->option('hours'), 1);
        $threshold = now()->subHours($hours);

        $affected = EconomicImport::query()
            ->whereIn('status', [
                EconomicImportStatus::Pending->value,
                EconomicImportStatus::Processing->value,
            ])
            ->where('created_at', '<=', $threshold)
            ->update([
                'status' => EconomicImportStatus::Failed->value,
                'error_code' => 'stale',
                'error_message' => __('app.economy.analysis.stale'),
                'finished_at' => now(),
            ]);

        $this->info(__('app.economy.analysis.pruned', ['count' => $affected]));

        return self::SUCCESS;
    }
}
