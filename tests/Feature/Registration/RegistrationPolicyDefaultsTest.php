<?php

namespace Tests\Feature\Registration;

use App\Contracts\RegistrationGate;
use App\Contracts\RegistrationPolicyDefaults;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Enums\RegistrationPolicy;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Auth\RegistrationSettings;
use App\Services\Auth\SelfHostedRegistrationGate;
use App\Services\Auth\SelfHostedRegistrationPolicyDefaults;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the pluggable admission-defaults seam.
 *
 * The property the commercial package relies on: swapping the
 * RegistrationPolicyDefaults binding changes the fallback for a fresh install
 * without touching RegistrationSettings or the gate, and never overrides a
 * stored policy.
 *
 * @see RegistrationPolicyDefaults
 * @see AppServiceProvider::configureRegistrationDefaults()
 */
class RegistrationPolicyDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The documented defaults and the gate implementation below are the
        // self-hosted ones. Bind them explicitly so the assertions name the
        // implementation under test instead of depending on whether the
        // commercial package is installed and has bound its own.
        $this->useSelfHostedRegistration();
    }

    public function test_each_channel_resolves_the_documented_self_hosted_default_through_the_contract(): void
    {
        $defaults = app(RegistrationPolicyDefaults::class);

        $this->assertInstanceOf(SelfHostedRegistrationPolicyDefaults::class, $defaults);

        $this->assertSame(RegistrationPolicy::Disabled, $defaults->defaultFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::Disabled, $defaults->defaultFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $defaults->defaultFor(RegistrationChannel::Google));
        $this->assertSame(RegistrationPolicy::Open, $defaults->defaultFor(RegistrationChannel::HouseholdInvitation));
        $this->assertSame(RegistrationPolicy::Open, $defaults->defaultFor(RegistrationChannel::AdminInvitation));

        // The documented defaults are what the settings service actually serves
        // with no stored rows, expressed through the very same contract.
        $settings = app(RegistrationSettings::class);

        $this->assertDatabaseCount('platform_settings', 0);
        $this->assertSame(RegistrationPolicy::Disabled, $settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::Disabled, $settings->policyFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Google));
    }

    public function test_a_different_defaults_binding_changes_the_fallback_without_touching_settings_or_gate(): void
    {
        // Baseline: the self-hosted fallback denies a brand-new web identity.
        $this->assertSame(
            RegistrationDecision::Denied,
            app(RegistrationGate::class)->evaluate(RegistrationChannel::Web)->decision,
        );

        $this->bindSaasBetaDefaults();

        // Fresh resolutions (both services are non-shared) see the new binding.
        $settings = app(RegistrationSettings::class);
        $gate = app(RegistrationGate::class);

        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Api));
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $settings->policyFor(RegistrationChannel::Google));

        // The gate class and its policy->decision mapping are untouched; only
        // the policy it was handed changed.
        $this->assertInstanceOf(SelfHostedRegistrationGate::class, $gate);

        $decision = $gate->evaluate(RegistrationChannel::Web);

        $this->assertSame(RegistrationDecision::ApprovalRequired, $decision->decision);
        $this->assertTrue($decision->requiresApproval);
        $this->assertTrue($decision->requiresEmailVerification);
        $this->assertSame(RegistrationPolicy::ApprovalRequired, $decision->policy);
    }

    public function test_a_stored_row_still_wins_over_any_defaults_implementation(): void
    {
        $this->bindSaasBetaDefaults();

        $settings = app(RegistrationSettings::class);
        $settings->setPolicy(RegistrationChannel::Web, RegistrationPolicy::Disabled);
        $settings->setPolicy(RegistrationChannel::Google, RegistrationPolicy::ExistingOnly);

        $this->assertSame(RegistrationPolicy::Disabled, $settings->policyFor(RegistrationChannel::Web));
        $this->assertSame(RegistrationPolicy::ExistingOnly, $settings->policyFor(RegistrationChannel::Google));

        $gate = app(RegistrationGate::class);

        $this->assertSame(
            RegistrationDecision::Denied,
            $gate->evaluate(RegistrationChannel::Web)->decision,
        );

        User::factory()->create(['email' => 'known@example.com']);

        $this->assertSame(
            RegistrationDecision::ExistingIdentity,
            $gate->evaluate(RegistrationChannel::Google, 'known@example.com')->decision,
        );
    }

    public function test_the_gate_keeps_the_self_hosted_implementation(): void
    {
        $this->assertInstanceOf(SelfHostedRegistrationGate::class, app(RegistrationGate::class));
    }

    public function test_the_defaults_binding_is_bind_if_so_a_package_binding_survives_registration(): void
    {
        // Package-discovered providers register BEFORE the application's own.
        // Simulate that order: a commercial provider binds first, then the
        // Community provider registers with its `bindIf`.
        $container = $this->freshContainer();

        $container->bind(RegistrationPolicyDefaults::class, PackageRegistrationPolicyDefaults::class);

        (new AppServiceProvider($container))->register();

        $this->assertInstanceOf(
            PackageRegistrationPolicyDefaults::class,
            $container->make(RegistrationPolicyDefaults::class),
            'A plain bind in AppServiceProvider would overwrite the package implementation.',
        );
    }

    public function test_a_later_package_binding_also_wins_when_community_registered_first(): void
    {
        $container = $this->freshContainer();

        (new AppServiceProvider($container))->register();

        $this->assertInstanceOf(
            SelfHostedRegistrationPolicyDefaults::class,
            $container->make(RegistrationPolicyDefaults::class),
        );

        // The other order must end on the package too, so the design does not
        // depend on which provider happens to run last.
        $container->bind(RegistrationPolicyDefaults::class, PackageRegistrationPolicyDefaults::class);

        $this->assertInstanceOf(
            PackageRegistrationPolicyDefaults::class,
            $container->make(RegistrationPolicyDefaults::class),
        );
    }

    public function test_an_unreadable_stored_value_falls_back_through_the_contract(): void
    {
        $this->bindSaasBetaDefaults();

        PlatformSetting::setString('registration.policy.api', 'not-a-policy');

        $this->assertSame(
            RegistrationPolicy::ApprovalRequired,
            app(RegistrationSettings::class)->policyFor(RegistrationChannel::Api),
        );
    }

    /**
     * Bind a SaaS-beta defaults double: web, api and google open at least to
     * `approval_required` while a beta runs.
     */
    private function bindSaasBetaDefaults(): void
    {
        $this->app->bind(RegistrationPolicyDefaults::class, fn (): RegistrationPolicyDefaults => new PackageRegistrationPolicyDefaults);
    }

    /**
     * Build a bare container carrying the same configuration the booted app
     * has, so the providers' `register()` methods behave identically.
     */
    private function freshContainer(): Container
    {
        $container = new Container;

        $container->instance('app', $container);
        $container->instance('config', new Repository(app('config')->all()));

        return $container;
    }
}

/**
 * A stand-in for the commercial package's admission defaults.
 *
 * It differs from the self-hosted defaults in exactly one respect: the opening
 * posture of a fresh install. The decision vocabulary and every other rule stay
 * in Community.
 */
class PackageRegistrationPolicyDefaults implements RegistrationPolicyDefaults
{
    public function defaultFor(RegistrationChannel $channel): RegistrationPolicy
    {
        return match ($channel) {
            RegistrationChannel::Web,
            RegistrationChannel::Api,
            RegistrationChannel::Google => RegistrationPolicy::ApprovalRequired,
            RegistrationChannel::HouseholdInvitation,
            RegistrationChannel::AdminInvitation => RegistrationPolicy::Open,
        };
    }
}
