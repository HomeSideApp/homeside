<?php

namespace App\Services\Auth;

use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Reads and writes the per-channel registration policy.
 *
 * The service is autowired and deliberately NOT a singleton: resolving it more
 * than once in a request must never hand back a stale object. Within a real
 * HTTP request it memoises reads on the request attributes so one request sees
 * a stable snapshot; from CLI, queue workers and tests (which have no real
 * request) it always performs a plain read, so a long-lived process can never
 * serve a stale policy.
 */
final class RegistrationSettings
{
    private const REQUEST_ATTRIBUTE = 'registration_settings.policies';

    private const KEY_PREFIX = 'registration.policy.';

    /**
     * Resolve the policy that governs the channel.
     *
     * Invitation channels are not policy-gated, so they always resolve to
     * `open`; the invitation flow enforces its own validity rules.
     *
     * @param  RegistrationChannel  $channel  The channel to resolve.
     * @return RegistrationPolicy The effective policy.
     */
    public function policyFor(RegistrationChannel $channel): RegistrationPolicy
    {
        if ($channel->isInvitation()) {
            return RegistrationPolicy::Open;
        }

        $stored = $this->read(self::KEY_PREFIX.$channel->value);

        if ($stored === null) {
            return $this->defaultFor($channel);
        }

        return RegistrationPolicy::tryFrom($stored) ?? $this->defaultFor($channel);
    }

    /**
     * Persist the policy for a policy-gated channel.
     *
     * @param  RegistrationChannel  $channel  The channel to configure.
     * @param  RegistrationPolicy  $policy  The policy to store.
     *
     * @throws InvalidArgumentException When the channel is not policy-gated.
     */
    public function setPolicy(RegistrationChannel $channel, RegistrationPolicy $policy): void
    {
        if (! $channel->isPolicyGated()) {
            throw new InvalidArgumentException("Channel [{$channel->value}] is not policy-gated.");
        }

        PlatformSetting::setString(self::KEY_PREFIX.$channel->value, $policy->value);

        $this->flush();
    }

    /**
     * Get the secure self-hosted default for a policy-gated channel.
     *
     * @param  RegistrationChannel  $channel  The channel to resolve.
     * @return RegistrationPolicy The default policy.
     */
    private function defaultFor(RegistrationChannel $channel): RegistrationPolicy
    {
        return match ($channel) {
            RegistrationChannel::Web, RegistrationChannel::Api => RegistrationPolicy::Disabled,
            RegistrationChannel::Google => RegistrationPolicy::ApprovalRequired,
            RegistrationChannel::HouseholdInvitation,
            RegistrationChannel::AdminInvitation => RegistrationPolicy::Open,
        };
    }

    /**
     * Read a setting, memoised on the request attributes when a real request exists.
     *
     * @param  string  $key  The settings key.
     * @return string|null The stored value.
     */
    private function read(string $key): ?string
    {
        $request = $this->request();

        if ($request === null) {
            return PlatformSetting::getString($key);
        }

        $snapshot = $request->attributes->get(self::REQUEST_ATTRIBUTE, []);

        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        if (! array_key_exists($key, $snapshot)) {
            $snapshot[$key] = PlatformSetting::getString($key);
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $snapshot);
        }

        /** @var string|null $value */
        $value = $snapshot[$key];

        return $value;
    }

    /**
     * Forget the request-scoped snapshot after a write.
     */
    private function flush(): void
    {
        $this->request()?->attributes->remove(self::REQUEST_ATTRIBUTE);
    }

    /**
     * Resolve the current HTTP request, or null outside a real request.
     *
     * @return Request|null The request when one exists.
     */
    private function request(): ?Request
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return null;
        }

        $request = app('request');

        return $request instanceof Request ? $request : null;
    }
}
