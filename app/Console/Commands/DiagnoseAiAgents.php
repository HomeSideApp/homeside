<?php

declare(strict_types=1);

namespace App\Console\Commands;

use HomeSide\AiAgents\Synchronizer\AgentSynchronizer;
use Illuminate\Console\Command;

/**
 * Diagnose inconsistencies between registered Agent classes and the ai_agents table.
 *
 * Reports:
 * - Classes that have no matching database row.
 * - Database rows that have no matching registered class.
 *
 * Does NOT modify any data.
 */
class DiagnoseAiAgents extends Command
{
    protected $signature = 'ai:diagnose-agents';

    protected $description = 'Diagnose inconsistencies between registered agents and the ai_agents table';

    public function handle(AgentSynchronizer $synchronizer): int
    {
        $this->info('Diagnosing AI agents...');

        $result = $synchronizer->diagnose();

        $this->newLine();

        if ($result['classes_without_rows'] === [] && $result['rows_without_classes'] === []) {
            $this->info('No inconsistencies found. All agents are synchronised.');
        }

        if ($result['classes_without_rows'] !== []) {
            $this->warn('Classes without database rows:');

            foreach ($result['classes_without_rows'] as $key => $class) {
                $this->line("  - {$key} → {$class}");
            }

            $this->newLine();
        }

        if ($result['rows_without_classes'] !== []) {
            $this->warn('Database rows without registered class:');

            foreach ($result['rows_without_classes'] as $key => $label) {
                $this->line("  - {$key} ({$label})");
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
