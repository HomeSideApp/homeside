<?php

namespace App\Support;

use App\Contracts\EntitlementGate;
use App\Models\User;

/**
 * Self-hosted fallback for the entitlement port: with no billing package
 * installed there is nothing to meter and every capability is available.
 */
final class NullEntitlementGate implements EntitlementGate
{
    public function assertFeature(User $user, string $feature): void {}

    /**
     * Return a deterministic canonical operation id.
     *
     * Nothing is persisted in this mode, so the value only has to be stable and
     * unambiguous for the pair (user, caller id). It mirrors the commercial
     * adapter's user-scoped shape so both modes agree on what a canonical id
     * looks like.
     */
    public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
    {
        return "{$user->id}:{$operationId}";
    }

    public function commitUsage(string $operationId): void {}

    public function releaseUsage(string $operationId): void {}

    public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void {}

    public function reserveTicketRecognition(User $user, string $importId): string
    {
        return 'unmetered:'.$importId;
    }
}
