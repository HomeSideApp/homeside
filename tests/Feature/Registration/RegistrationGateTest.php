<?php

namespace Tests\Feature\Registration;

use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Auth\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers the registration policy vocabulary and the self-hosted gate.
 */
class RegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationGate $gate;

    private RegistrationSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        // This class asserts the self-hosted policy vocabulary and defaults.
        // Bind them explicitly so the commercial package's registration
        // defaults/gate cannot leak in when it is installed.
        $this->useSelfHostedRegistration();

        $this->gate = app(RegistrationGate::class);
        $this->settings = app(RegistrationSettings::class);
    }

    public function test_secure_self_hosted_defaults_are_used_when_no_row_exists(): void
    {
        $this->assertSame(RegistrationPolicy::Disabled, $this->settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::Disabled, $this->settings->policyFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $this->settings->policyFor(RegistrationChannel::Google));
        $this->assertSame(RegistrationPolicy::Open, $this->settings->policyFor(RegistrationChannel::HouseholdInvitation));
        $this->assertSame(RegistrationPolicy::Open, $this->settings->policyFor(RegistrationChannel::AdminInvitation));
    }

    public function test_the_settings_service_is_not_a_shared_singleton(): void
    {
        $this->assertNotSame(app(RegistrationSettings::class), app(RegistrationSettings::class));
    }

    public function test_setting_a_policy_on_an_invitation_channel_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->setPolicy(RegistrationChannel::HouseholdInvitation, RegistrationPolicy::Disabled);
    }

    public function test_every_channel_and_policy_combination_maps_to_the_expected_decision(): void
    {
        $this->assertEveryChannelAndPolicyMapsToTheExpectedDecision();
    }

    public function test_the_decision_mapping_is_unaffected_by_the_defaults_binding(): void
    {
        // A different defaults implementation only changes what the fallback is
        // when nothing is stored; it must never change how a policy maps to a
        // decision. Re-resolve the consumers so they pick up the new binding.
        app()->bind(RegistrationPolicyDefaults::class, fn (): RegistrationPolicyDefaults => new class implements RegistrationPolicyDefaults
        {
            public function defaultFor(RegistrationChannel $channel): RegistrationPolicy
            {
                return RegistrationPolicy::ApprovalRequired;
            }
        });

        $this->settings = app(RegistrationSettings::class);
        $this->gate = app(RegistrationGate::class);

        $this->assertEveryChannelAndPolicyMapsToTheExpectedDecision();
    }

    /**
     * Assert the full channel x policy decision matrix.
     *
     * Extracted so both the self-hosted defaults and a swapped defaults binding
     * run the exact same matrix instead of two parallel copies.
     */
    private function assertEveryChannelAndPolicyMapsToTheExpectedDecision(): void
    {
        foreach (RegistrationChannel::cases() as $channel) {
            if ($channel->isInvitation()) {
                $this->assertSame(
                    RegistrationPolicy::Open,
                    $this->settings->policyFor($channel),
                    "Invitation channel {$channel->value} must always resolve to open."
                );

                $decision = $this->gate->evaluate($channel, 'brand-new@example.com');

                $this->assertSame(RegistrationDecision::Allowed, $decision->decision, "Invitation channel {$channel->value} must never be denied.");
                $this->assertFalse($decision->requiresApproval);
                $this->assertFalse($decision->requiresEmailVerification);
                $this->assertSame($channel, $decision->channel);

                continue;
            }

            foreach (RegistrationPolicy::cases() as $policy) {
                $this->settings->setPolicy($channel, $policy);

                $decision = $this->gate->evaluate($channel, 'brand-new@example.com');

                [$expectedDecision, $requiresApproval, $requiresEmailVerification] = match ($policy) {
                    RegistrationPolicy::Disabled => [RegistrationDecision::Denied, false, false],
                    RegistrationPolicy::Open => [RegistrationDecision::Allowed, false, true],
                    RegistrationPolicy::ApprovalRequired => [RegistrationDecision::ApprovalRequired, true, true],
                    RegistrationPolicy::ExistingOnly => [RegistrationDecision::Denied, false, false],
                };

                $this->assertSame($expectedDecision, $decision->decision, "{$channel->value} + {$policy->value} mapped to the wrong decision.");
                $this->assertSame($policy, $decision->policy);
                $this->assertSame($channel, $decision->channel);
                $this->assertSame($requiresApproval, $decision->requiresApproval);
                $this->assertSame($requiresEmailVerification, $decision->requiresEmailVerification);
            }
        }
    }

    public function test_existing_only_allows_a_known_identity(): void
    {
        $known = User::factory()->create(['email' => 'known@example.com']);
        $this->settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::ExistingOnly);

        $decision = $this->gate->evaluate(RegistrationChannel::Google, 'known@example.com');

        $this->assertSame(RegistrationDecision::ExistingIdentity, $decision->decision);
        $this->assertStringContainsString('exists', (string) $decision->reason);
    }

    public function test_existing_only_denies_an_unknown_identity(): void
    {
        $this->settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::ExistingOnly);

        $decision = $this->gate->evaluate(RegistrationChannel::Google, 'unknown@example.com');

        $this->assertSame(RegistrationDecision::Denied, $decision->decision);
        $this->assertSame('registration_existing_only', $decision->reason);
    }

    public function test_an_unknown_stored_value_falls_back_to_the_secure_default(): void
    {
        PlatformSetting::setString('registration.policy.api', 'not-a-policy');

        $this->assertSame(RegistrationPolicy::Disabled, $this->settings->policyFor(RegistrationChannel::Api));
    }

    public function test_a_stored_policy_is_read_back(): void
    {
        $this->settings->setPolicy(RegistrationChannel::Api, RegistrationPolicy::Open);

        $this->assertSame(RegistrationPolicy::Open, $this->settings->policyFor(RegistrationChannel::Api));
    }
}
