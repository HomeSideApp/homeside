<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use Database\Seeders\E2EDatabaseSeeder;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Seeds the CONTROLLED entitlement plans the household-capacity browser
 * scenarios depend on and assigns them to fixtures.
 *
 * The commercial package ships production plan values (free 2, plus 5, pro 10,
 * max 20). A capacity scenario must never assert against those: the fixture
 * seeds its own small/medium/large plans with KNOWN, CONTROLLED limits (1/2/3)
 * and assigns one to a fixture account. No commercial quantity is hardcoded in
 * application code; the controlled limits belong to the E2E seeder only.
 *
 * The command is deliberately package-agnostic: all commercial access lives in
 * {@see E2EDatabaseSeeder}, so Community code never imports a SaaS class.
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
    public function handle(E2EDatabaseSeeder $fixtures): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
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
     * @param  E2EDatabaseSeeder  $fixtures  The E2E fixture seeder.
     */
    private function seed(E2EDatabaseSeeder $fixtures): void
    {
        $fixtures->seedControlledPlans();

        $this->info('Controlled E2E plans seeded: '.implode(', ', array_keys(E2EDatabaseSeeder::CONTROLLED_PLAN_QUOTAS)).'.');
    }

    /**
     * Assign a controlled plan to a fixture account and seed its owned state.
     *
     * @param  E2EDatabaseSeeder  $fixtures  The E2E fixture seeder.
     *
     * @throws InvalidArgumentException When the email or plan key is missing.
     */
    private function grant(E2EDatabaseSeeder $fixtures): void
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $planKey = (string) $this->argument('plan');

        if ($email === '' || $planKey === '') {
            throw new InvalidArgumentException('grant requires an email and a plan key.');
        }

        $fixtures->grantControlledPlan(
            $email,
            $planKey,
            max(0, (int) $this->option('own')),
            $this->option('reset') === true,
        );

        $this->info(sprintf('Granted %s to %s.', $planKey, $email));
    }
}
