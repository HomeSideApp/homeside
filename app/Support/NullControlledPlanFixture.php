<?php

namespace App\Support;

use App\Contracts\ControlledPlanFixture;
use App\Models\User;

/**
 * Self-hosted fallback for the E2E controlled-plan port: a Community checkout
 * has nothing commercial to seed, so the scenarios that need it skip
 * themselves instead of failing.
 */
final class NullControlledPlanFixture implements ControlledPlanFixture
{
    /**
     * Declare no plans: the empty list is how the absent module is expressed.
     *
     * @return list<string>
     */
    public function planKeys(): array
    {
        return [];
    }

    public function seed(): void {}

    public function grant(string $email, string $planKey, int $own = 0, bool $reset = false): void {}

    /**
     * @return array{plan_key: string|null, households_own_cap: int|null}
     */
    public function stateFor(User $user): array
    {
        return ['plan_key' => null, 'households_own_cap' => null];
    }
}
