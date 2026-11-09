<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Contract for the controlled plan fixture the browser suite drives.
 *
 * This is a capability port for E2E-only tooling, never a production feature.
 * The app's seeder and E2E commands depend on this port only: when the
 * homeside/saas package is installed the container binds its commercial
 * implementation, otherwise it binds App\Support\NullControlledPlanFixture.
 *
 * The absence of the optional module needs no `class_exists` and no
 * try/catch: `planKeys()` returning an empty list IS that statement, and the
 * scenarios that need commercial plans skip themselves.
 */
interface ControlledPlanFixture
{
    /**
     * List the controlled plan keys this checkout can hand out.
     *
     * @return list<string> The plan keys, or an empty list when the optional module is not installed.
     */
    public function planKeys(): array;

    /**
     * Create or refresh the controlled plans the scenarios grant.
     */
    public function seed(): void;

    /**
     * Assign a controlled plan to a fixture account and seed its owned state.
     *
     * @param  string  $email  The fixture account email.
     * @param  string  $planKey  The controlled plan key to grant.
     * @param  int  $own  The number of households the account already owns.
     * @param  bool  $reset  Delete the account's owned households and active grants first.
     */
    public function grant(string $email, string $planKey, int $own = 0, bool $reset = false): void;

    /**
     * Report the account's effective plan key and household-ownership cap.
     *
     * Both values are null when the optional module is not installed, so the
     * caller reads "no entitlement to assert against" instead of guessing.
     *
     * @param  User  $user  The fixture account to inspect.
     * @return array{plan_key: string|null, households_own_cap: int|null} The entitlement state.
     */
    public function stateFor(User $user): array;
}
