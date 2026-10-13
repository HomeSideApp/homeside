<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Console\Command;

/**
 * Flips a registration channel policy on the isolated E2E database.
 *
 * Scenarios need different channel policies (E2E-01 proves the secure defaults
 * refuse registration; E2E-02 registers under `approval_required`), so the
 * suite drives the real settings service instead of writing the
 * `platform_settings` row by hand.
 */
class E2EPolicyCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:policy {channel : web|api|google} {policy : disabled|approval_required|open|existing_only}';

    protected $description = 'Set a registration channel policy on the E2E database';

    /**
     * Execute the command.
     */
    public function handle(RegistrationSettings $settings): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $channel = RegistrationChannel::tryFrom((string) $this->argument('channel'));
        $policy = RegistrationPolicy::tryFrom((string) $this->argument('policy'));

        if ($channel === null || ! $channel->isPolicyGated()) {
            $this->error('Unknown or non-policy channel.');

            return self::FAILURE;
        }

        if ($policy === null) {
            $this->error('Unknown policy.');

            return self::FAILURE;
        }

        return $this->runGuarded(function () use ($settings, $channel, $policy): void {
            $settings->setPolicy($channel, $policy);

            $this->info(sprintf('Registration policy for %s set to %s.', $channel->value, $policy->value));
        });
    }
}
