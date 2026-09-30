<?php

declare(strict_types=1);

namespace App\Console\Commands;

use HomeSide\AiAgents\Synchronizer\AgentSynchronizer;
use Illuminate\Console\Command;

/**
 * Synchronise registered Agent classes with the ai_agents table.
 *
 * This command is idempotent: it creates missing rows, flags orphaned rows,
 * and reports classes without matching database entries.
 *
 * @see HomeSide\AiAgents\Synchronizer\AgentSynchronizer
 */
class SyncAiAgents extends Command
{
    protected $signature = 'ai:sync-agents';

    protected $description = 'Synchronise registered Agent classes with the ai_agents table';

    public function handle(AgentSynchronizer $synchronizer): int
    {
        $this->info('Synchronising AI agents...');

        $result = $synchronizer->sync();

        $this->newLine();
        $this->info("Created: {$result['created']}");
        $this->info("Unchanged: {$result['unchanged']}");

        if ($result['orphaned_keys'] !== []) {
            $this->newLine();
            $this->warn('Orphaned rows (in DB but no registered class):');

            foreach ($result['orphaned_keys'] as $key) {
                $this->line("  - {$key}");
            }
        }

        if ($result['missing_rows'] !== []) {
            $this->newLine();
            $this->warn('Classes without database rows:');

            foreach ($result['missing_rows'] as $key) {
                $this->line("  - {$key}");
            }
        }

        if ($result['orphaned_keys'] === [] && $result['missing_rows'] === []) {
            $this->newLine();
            $this->info('All agents are synchronised.');
        }

        return self::SUCCESS;
    }
}
