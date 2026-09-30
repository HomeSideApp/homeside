<?php

namespace App\Console\Commands;

use App\Jobs\SyncCryptoPrices;
use Illuminate\Console\Command;

/**
 * Schedules the crypto price synchronisation from the console.
 */
final class SyncCryptoPricesCommand extends Command
{
    protected $signature = 'economy:sync-crypto-prices {--sync : Run the synchronisation inline instead of queueing it}';

    protected $description = 'Fetch and store the latest crypto price snapshots';

    /**
     * Dispatch the crypto price synchronisation job.
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        if ($this->option('sync')) {
            dispatch_sync(new SyncCryptoPrices);
        } else {
            SyncCryptoPrices::dispatch();
        }

        $this->info(__('app.economy.crypto.price_sync_queued'));

        return self::SUCCESS;
    }
}
