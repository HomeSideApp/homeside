<?php

namespace App\Services\Auth;

use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\HouseholdCreationPolicy;
use App\Enums\InvitationPolicy;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationPolicy;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Reads and writes the platform registration policy.
 *
 * The service owns the whole settings surface: the per-channel policies, the
 * invitation policies and the household-creation policy. When no row exists for
 * a policy-gated channel the fallback comes from the injected
 * {@see RegistrationPolicyDefaults} contract, so a different distribution can
 * open a fresh install differently without duplicating any decision logic.
 *
 * Stored rows always win over the defaults; the defaults are consulted only
 * when there is no readable value to honour. The invitation defaults
 * (`enabled`) and the self-hosted household-creation default (`allowed`) stay
 * here: they describe the invitation vocabulary and the household axis, while
 * the injected contract is about channel admission.
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

    private const INVITATION_KEY_PREFIX = 'registration.invitations.';

    private const HOUSEHOLD_CREATION_KEY = 'registration.household_creation';

    /**
     * Bind the admission fallback behind its contract.
     *
     * The contract is injected rather than resolved at call time so every fresh
     * `RegistrationSettings` observes the current binding (autowiring gives a
     * new defaults instance per resolution; nothing is cached across requests).
     *
     * @param  RegistrationPolicyDefaults  $defaults  The fallback policy source.
     */
    public function __construct(private RegistrationPolicyDefaults $defaults) {}

    /**
     * Resolve the policy that governs the channel.
     *
     * Invitation channels map their InvitationPolicy onto the same
     * RegistrationPolicy vocabulary this method has always returned, so the
     * registration gate and every existing caller keep one type: `enabled`
     * becomes `open` and `disabled` becomes `disabled`. The invitation flow
     * still enforces its own validity rules (token, expiry, remaining uses).
     *
     * @param  RegistrationChannel  $channel  The channel to resolve.
     * @return RegistrationPolicy The effective policy.
     */
    public function policyFor(RegistrationChannel $channel): RegistrationPolicy
    {
        if ($channel->isInvitation()) {
            return $this->invitationPolicyFor($channel)->allowsRegistration()
                ? RegistrationPolicy::Open
                : RegistrationPolicy::Disabled;
        }

        $stored = $this->read(self::KEY_PREFIX.$channel->value);

        if ($stored === null) {
            return $this->defaults->defaultFor($channel);
        }

        return RegistrationPolicy::tryFrom($stored) ?? $this->defaults->defaultFor($channel);
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
     * List the registration policies a channel can actually honour.
     *
     * Google can recognise an existing identity, so it accepts `existing_only`.
     * Web and API cannot recognise an identity before a password is set, where
     * that value would collapse into `disabled`, so it is refused explicitly
     * instead of silently misrepresenting the policy.
     *
     * @param  RegistrationChannel  $channel  The policy-gated channel to inspect.
     * @return list<RegistrationPolicy> The policies the channel accepts.
     *
     * @throws InvalidArgumentException When the channel is not policy-gated.
     */
    public function allowedPoliciesFor(RegistrationChannel $channel): array
    {
        if (! $channel->isPolicyGated()) {
            throw new InvalidArgumentException("Channel [{$channel->value}] is not policy-gated.");
        }

        return match ($channel) {
            RegistrationChannel::Google => RegistrationPolicy::cases(),
            default => [
                RegistrationPolicy::Disabled,
                RegistrationPolicy::ApprovalRequired,
                RegistrationPolicy::Open,
            ],
        };
    }

    /**
     * Resolve the invitation policy for an invitation channel.
     *
     * @param  RegistrationChannel  $channel  The invitation channel to resolve.
     * @return InvitationPolicy The effective invitation policy.
     *
     * @throws InvalidArgumentException When the channel is not an invitation channel.
     */
    public function invitationPolicyFor(RegistrationChannel $channel): InvitationPolicy
    {
        if (! $channel->isInvitation()) {
            throw new InvalidArgumentException("Channel [{$channel->value}] is not an invitation channel.");
        }

        $stored = $this->read(self::INVITATION_KEY_PREFIX.$channel->value);

        return InvitationPolicy::tryFrom((string) $stored) ?? InvitationPolicy::Enabled;
    }

    /**
     * Persist the invitation policy for an invitation channel.
     *
     * @param  RegistrationChannel  $channel  The channel to configure.
     * @param  InvitationPolicy  $policy  The policy to store.
     *
     * @throws InvalidArgumentException When the channel is not an invitation channel.
     */
    public function setInvitationPolicy(RegistrationChannel $channel, InvitationPolicy $policy): void
    {
        if (! $channel->isInvitation()) {
            throw new InvalidArgumentException("Channel [{$channel->value}] is not an invitation channel.");
        }

        PlatformSetting::setString(self::INVITATION_KEY_PREFIX.$channel->value, $policy->value);

        $this->flush();
    }

    /**
     * Determine whether an invitation channel may start a registration.
     *
     * @param  RegistrationChannel  $channel  The invitation channel to check.
     * @return bool True when the invitation channel is enabled.
     */
    public function invitationsEnabled(RegistrationChannel $channel): bool
    {
        return $this->invitationPolicyFor($channel)->allowsRegistration();
    }

    /**
     * Resolve the installation's household-creation policy.
     *
     * @return HouseholdCreationPolicy The effective policy.
     */
    public function householdCreationPolicy(): HouseholdCreationPolicy
    {
        $stored = $this->read(self::HOUSEHOLD_CREATION_KEY);

        return HouseholdCreationPolicy::tryFrom((string) $stored) ?? HouseholdCreationPolicy::Allowed;
    }

    /**
     * Persist the installation's household-creation policy.
     *
     * @param  HouseholdCreationPolicy  $policy  The policy to store.
     */
    public function setHouseholdCreationPolicy(HouseholdCreationPolicy $policy): void
    {
        PlatformSetting::setString(self::HOUSEHOLD_CREATION_KEY, $policy->value);

        $this->flush();
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
