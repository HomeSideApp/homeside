<?php

namespace Tests\Feature\Households\Support;

use App\Contracts\EntitlementGate;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Recording double for the app-owned EntitlementGate port.
 *
 * It records every ownership-capacity assertion together with the database
 * transaction level it observed, so a test can prove the contract:
 *  - the structural policy never reads capacity, so the only capacity assertion
 *    is the authoritative one INSIDE the creation transaction (level
 *    `base + 1`).
 *
 * `denyAfterCapacityCalls` lets a test model a quota refusal that is not a
 * structural denial (0 = refuse the single in-transaction assertion), which is
 * exactly what must render as 402 rather than 403.
 */
final class RecordingEntitlementGate implements EntitlementGate
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<int> */
    public array $capacityLevels = [];

    /**
     * When set, the N-th capacity assertion (1-based) and every later one is
     * refused; the first N-1 pass.
     */
    public ?int $denyAfterCapacityCalls = null;

    /** @var list<int> */
    public array $incomingAmounts = [];

    public function assertFeature(User $user, string $feature): void
    {
        $this->calls[] = "assertFeature:{$feature}";
    }

    public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
    {
        $this->calls[] = "reserveUsage:{$metric}";

        return "{$user->id}:{$operationId}";
    }

    public function commitUsage(string $operationId): void
    {
        $this->calls[] = "commitUsage:{$operationId}";
    }

    public function releaseUsage(string $operationId): void
    {
        $this->calls[] = "releaseUsage:{$operationId}";
    }

    public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void
    {
        $this->calls[] = "household_capacity:{$incoming}";
        $this->incomingAmounts[] = $incoming;
        $this->capacityLevels[] = DB::transactionLevel();

        if ($this->denyAfterCapacityCalls !== null && count($this->capacityLevels) > $this->denyAfterCapacityCalls) {
            throw new EntitlementQuotaExceededException($user->id, 'households_own', 0, 0, $incoming);
        }
    }

    public function reserveTicketRecognition(User $user, string $importId): string
    {
        $this->calls[] = "reserveTicketRecognition:{$importId}";

        return "unmetered:{$importId}";
    }

    public function capacityCallCount(): int
    {
        return count($this->capacityLevels);
    }
}
