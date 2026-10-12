<?php

namespace App\Services\Auth;

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
 * The service owns the whole settings surface and the secure self-hosted
 * defaults. Defaults and their rationale:
 *
 *  - Web registration: `disabled`. A password form is the easiest channel to
 *    abuse on a public host, so it stays off until the operator asks for it.
 *  - API registration: `disabled`. Same reasoning, and the endpoint is
 *    reachable without a browser.
 *  - Google registration: `approval_required`. Federated sign-in is convenient,
 *    but the identity is external, so an administrator decides.
 *  - Household invitations: `enabled`. Inviting people is the normal onboarding
 *    path of a (private) self-hosted install, and the invitation itself carries
 *    the operator's decision, so no extra gate is needed.
 *  - Admin invitations: `enabled`. An administrator already has the authority
 *    to create accounts; refusing the flow by default only breaks onboarding.
 *  - Self-hosted household creation: `allowed`. The operator chose to run the
 *    install for their own household, and the per-user tri-state override plus
 *    the entitlement gate still apply, so `allowed` delegates rather than grants.
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
