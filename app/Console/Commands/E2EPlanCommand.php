<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Contracts\ControlledPlanFixture;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Seeds the CONTROLLED entitlement plans the household-capacity browser
 * scenarios depend on and assigns them to fixtures.
 *
 * The commercial implementation ships production plan values (free 2, plus 5,
 * pro 10, max 20). A capacity scenario must never assert against those: the
 * fixture seeds its own small/medium/large plans with KNOWN, CONTROLLED limits
 * (1/2/3) and assigns one to a fixture account.
 *
 * All commercial access lives behind the app-owned
 * {@see ControlledPlanFixture} port. The controlled limits belong to the
 * commercial implementation of that port, never to application code: this
 * command only names the port.
 */
class E2EPlanCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:plan
        {action : seed|grant}
        {email? : The fixture account to grant a plan to (grant only)}
        {plan? : The controlled plan key to grant (grant only)}
        {--own=0 : The number of households the account already owns}
        {--reset : Delete the account\'s owned households and active grants first}';

    protected $description = 'Seed the controlled E2E entitlement plans and assign them to fixtures';

    /**
     * Execute the command.
     */
    public function handle(ControlledPlanFixture $fixtures): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        // An empty plan list is how the absent optional module is expressed. On
        // a Community checkout there is nothing to seed, so report that and
        // succeed instead of erroring; the commercial browser scenarios then
        // skip themselves.
        if ($fixtures->planKeys() === []) {
            $this->info('The optional homeside/saas module is not installed; the controlled E2E plans are unavailable and nothing was seeded.');

            return self::SUCCESS;
        }

        return $this->runGuarded(function () use ($fixtures): void {
            match ((string) $this->argument('action')) {
                'seed' => $this->seed($fixtures),
                'grant' => $this->grant($fixtures),
                default => throw new InvalidArgumentException('Unknown action; expected seed or grant.'),
            };
        });
    }

    /**
     * Create or refresh the controlled plans.
     *
     * @param  ControlledPlanFixture  $fixtures  The controlled plan fixture port.
     */
    private function seed(ControlledPlanFixture $fixtures): void
    {
        $fixtures->seed();

        $this->info('Controlled E2E plans seeded: '.implode(', ', $fixtures->planKeys()).'.');
    }

    /**
     * Assign a controlled plan to a fixture account and seed its owned state.
     *
     * @param  ControlledPlanFixture  $fixtures  The controlled plan fixture port.
     *
     * @throws InvalidArgumentException When the email or plan key is missing.
     */
    private function grant(ControlledPlanFixture $fixtures): void
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $planKey = (string) $this->argument('plan');

        if ($email === '' || $planKey === '') {
            throw new InvalidArgumentException('grant requires an email and a plan key.');
        }

        $fixtures->grant(
            $email,
            $planKey,
            max(0, (int) $this->option('own')),
            $this->option('reset') === true,
        );

        $this->info(sprintf('Granted %s to %s.', $planKey, $email));
    }
}
