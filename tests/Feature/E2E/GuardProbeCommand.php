<?php

namespace Tests\Feature\E2E;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use Illuminate\Console\Command;

/**
 * Test-only command that exposes the E2E database guard without running any of
 * its destructive work, so the guard can be asserted in isolation.
 */
class GuardProbeCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:guard-probe';

    protected $description = 'Expose the E2E database guard for tests';

    /**
     * Expose the protected guard decision.
     */
    public function probe(): bool
    {
        return $this->guardE2EDatabase();
    }
}
