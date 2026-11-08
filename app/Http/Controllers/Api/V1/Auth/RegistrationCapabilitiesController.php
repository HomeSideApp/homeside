<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Data\Auth\RegistrationCapabilitiesData;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\RegistrationCapabilitiesResource;
use App\Services\Auth\RegistrationSettings;

/**
 * Publishes the installation's public registration capabilities.
 *
 * The payload lets a mobile client build its UI without hardcoding which
 * channels exist or whether approval is required. It is INFORMATION, never
 * authorisation: every mutating endpoint re-evaluates the registration gate, so
 * a stale capability can never open a channel.
 */
final class RegistrationCapabilitiesController extends Controller
{
    /**
     * Return the capabilities the installation currently offers.
     *
     * @param  RegistrationSettings  $settings  The registration settings service.
     * @return RegistrationCapabilitiesResource The capabilities resource.
     */
    public function show(RegistrationSettings $settings): RegistrationCapabilitiesResource
    {
        $channels = [];

        foreach ([RegistrationChannel::Web, RegistrationChannel::Api, RegistrationChannel::Google] as $channel) {
            $policy = $settings->policyFor($channel);

            $channels[$channel->value] = [
                'available' => $policy !== RegistrationPolicy::Disabled,
                'policy' => $policy->value,
                'approval_required' => $policy->requiresApproval(),
            ];
        }

        return new RegistrationCapabilitiesResource(new RegistrationCapabilitiesData(
            channels: $channels,
            googleAvailable: $channels[RegistrationChannel::Google->value]['available']
                && filled(config('services.google.client_id')),
        ));
    }
}
