<?php

namespace Tests\Feature\Registration;

use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Services\Auth\RegistrationSettings;
use App\Services\Auth\SelfHostedRegistrationGate;
use HomeSide\Saas\Services\SaasRegistrationGate;
use HomeSide\Saas\Support\SaasRegistrationPolicyDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The commercial counterpart of the self-hosted defaults.
 *
 * Section 13 of the registration spec requires the suite to run in two
 * profiles: an OSS suite that is runnable without the commercial package, and a
 * SaaS suite that runs when the package is installed. The self-hosted half is
 * covered by RegistrationPolicyDefaultsTest and RegistrationGateTest; this
 * class asserts the other half: WHEN the commercial implementations are bound,
 * the same channels (web, api, google) fall back to `approval_required` and the
 * gate resolves to the package class.
 *
 * The suite is green in both profiles because the self-hosted tests bind their
 * own implementation and this class binds the commercial one. This class skips
 * cleanly when the package is not installed (there is nothing commercial to
 * exercise) and under the `selfhosted` profile (run `make test-saas` instead),
 * so a skip is never presented as commercial coverage.
 */
class SaasRegistrationProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(SaasRegistrationPolicyDefaults::class) || ! class_exists(SaasRegistrationGate::class)) {
            $this->markTestSkipped('The homeside/saas package is not installed.');
        }

        if (static::registrationProfile() === 'selfhosted') {
            $this->markTestSkipped('The self-hosted profile does not exercise the commercial package; run `make test-saas`.');
        }
    }

    public function test_the_commercial_defaults_open_the_same_channels_for_approval(): void
    {
        $this->useSaasRegistration();

        $defaults = app(RegistrationPolicyDefaults::class);

        $this->assertInstanceOf(SaasRegistrationPolicyDefaults::class, $defaults);
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $defaults->defaultFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $defaults->defaultFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $defaults->defaultFor(RegistrationChannel::Google));

        // The very same settings service the self-hosted tests assert resolves
        // the commercial fallback when no row is stored.
        $settings = app(RegistrationSettings::class);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Google));
    }

    public function test_the_commercial_gate_resolves_to_the_package_implementation(): void
    {
        $this->useSaasRegistration();

        $gate = app(RegistrationGate::class);

        $this->assertInstanceOf(SaasRegistrationGate::class, $gate);
        $this->assertNotInstanceOf(SelfHostedRegistrationGate::class, $gate);

        // The package composes the Community gate, so the policy-to-decision
        // mapping is unchanged: only the fallback policy is commercial.
        $decision = $gate->evaluate(RegistrationChannel::Web);

        $this->assertSame(RegistrationDecision::ApprovalRequired, $decision->decision);
        $this->assertTrue($decision->requiresApproval);
        $this->assertTrue($decision->requiresEmailVerification);
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $decision->policy);
    }
}
