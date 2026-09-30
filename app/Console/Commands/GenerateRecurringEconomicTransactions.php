<?php

namespace App\Console\Commands;

use App\Actions\Economy\GenerateRecurringTransactions;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('economy:generate-recurring-transactions {--date=}')]
final class GenerateRecurringEconomicTransactions extends Command
{
    /**
     * Create the recurring transaction command with its localized description.
     *
     * @return void This constructor does not return a value.
     */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('console.economy.recurring.description'));
    }

    /**
     * Generate recurring transaction occurrences that are due.
     *
     * @param  GenerateRecurringTransactions  $action  The action that generates all due occurrences.
     * @return int The console command exit status.
     */
    public function handle(GenerateRecurringTransactions $action): int
    {
        try {
            $through = $this->option('date')
                ? Carbon::parse((string) $this->option('date'))
                : now();
        } catch (InvalidFormatException) {
            $this->components->error(__('console.economy.recurring.invalid_date'));

            return self::FAILURE;
        }

        $count = $action->execute($through);
        $this->components->info(trans_choice(
            'console.economy.recurring.generated',
            $count,
            ['count' => $count],
        ));

        return self::SUCCESS;
    }
}
