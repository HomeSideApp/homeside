<?php

namespace Database\Seeders;

use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Database\Seeder;

/**
 * Writes the secure self-hosted registration defaults idempotently.
 *
 * Invitation channels are intentionally omitted: they are not policy-gated and
 * always resolve to open, so they have no stored policy.
 */
class RegistrationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(RegistrationSettings::class);

        $defaults = [
            RegistrationChannel::Web->value => RegistrationPolicy::Disabled,
            RegistrationChannel::Api->value => RegistrationPolicy::Disabled,
            RegistrationChannel::Google->value => RegistrationPolicy::ApprovalRequired,
        ];

        foreach ($defaults as $channel => $policy) {
            $settings->setPolicy(RegistrationChannel::from($channel), $policy);
        }
    }
}
