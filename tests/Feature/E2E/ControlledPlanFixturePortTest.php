<?php

namespace Tests\Feature\E2E;

use App\Contracts\ControlledPlanFixture;
use App\Models\User;
use App\Support\NullControlledPlanFixture;
use Tests\TestCase;

/**
 * Proves the E2E controlled-plan tooling depends on an app-owned port instead
 * of the optional commercial package.
 *
 * Which implementation the container resolves depends on whether the module is
 * installed, so the resolution test only asserts the port is always resolvable
 * and that it satisfies its own contract. The Community behaviour is then
 * proven explicitly through the null fixture, never through a binding
 * assumption.
 */
class ControlledPlanFixturePortTest extends TestCase
{
    public function test_container_resolves_a_controlled_plan_fixture_implementation(): void
    {
        $fixture = $this->app->make(ControlledPlanFixture::class);

        // The concrete class depends on whether the optional commercial module is installed, so
        // this asserts the port is always resolvable without pinning a mode-specific implementation.
        $this->assertInstanceOf(ControlledPlanFixture::class, $fixture);
    }

    public function test_null_fixture_declares_no_plans(): void
    {
        $this->app->instance(ControlledPlanFixture::class, new NullControlledPlanFixture);

        $this->assertSame([], $this->app->make(ControlledPlanFixture::class)->planKeys());
    }

    public function test_bind_if_does_not_override_an_existing_binding(): void
    {
        $double = new class implements ControlledPlanFixture
        {
            /** @return list<string> */
            public function planKeys(): array
            {
                return ['double-plan'];
            }

            public function seed(): void {}

            public function grant(string $email, string $planKey, int $own = 0, bool $reset = false): void {}

            /** @return array{plan_key: string|null, households_own_cap: int|null} */
            public function stateFor(User $user): array
            {
                return ['plan_key' => 'double-plan', 'households_own_cap' => 7];
            }
        };

        $this->app->bind(ControlledPlanFixture::class, fn (): ControlledPlanFixture => $double);

        $this->app->bindIf(ControlledPlanFixture::class, NullControlledPlanFixture::class);

        // The commercial module binds its own implementation first; this proves the Community
        // default can never overwrite it, whichever order the providers register in.
        $this->assertSame($double, $this->app->make(ControlledPlanFixture::class));
    }

    public function test_null_fixture_seeding_and_granting_leave_the_account_unentitled(): void
    {
        $user = User::factory()->make(['email' => 'plan.small@homeside.test']);
        $fixture = new NullControlledPlanFixture;

        $fixture->seed();
        $fixture->grant((string) $user->email, 'e2e-small', 2, true);

        $this->assertSame(
            ['plan_key' => null, 'households_own_cap' => null],
            $fixture->stateFor($user),
        );
    }
}
