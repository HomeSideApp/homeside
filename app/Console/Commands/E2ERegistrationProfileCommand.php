<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2EDatabase;
use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\RegistrationChannel;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Console\Command;

/**
 * Prints the registration profile the E2E application server actually resolves.
 *
 * The browser scenarios describe a COMMERCIAL (SaaS) deployment: the package is
 * installed, so the container must resolve `SaasRegistrationGate` and
 * `SaasRegistrationPolicyDefaults` while Community keeps the decision rules.
 * Asserting the resolved class names from the running server is how the suite
 * proves it is exercising the SaaS admission path rather than a Community
 * fallback that happens to answer the same way.
 */
class E2ERegistrationProfileCommand extends Command
{
    use GuardsE2EDatabase;

    protected $signature = 'e2e:registration-profile';

    protected $description = 'Print the resolved registration gate, defaults and effective channel policies';

    /**
     * Execute the command.
     */
    public function handle(RegistrationSettings $settings): int
    {
        if (! $this->guardE2EDatabase()) {
            return self::FAILURE;
        }

        $this->line(json_encode([
            'gate' => get_class(app(RegistrationGate::class)),
            'defaults' => get_class(app(RegistrationPolicyDefaults::class)),
            'policies' => [
                'web' => $settings->policyFor(RegistrationChannel::Web)->value,
                'api' => $settings->policyFor(RegistrationChannel::Api)->value,
                'google' => $settings->policyFor(RegistrationChannel::Google)->value,
            ],
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
