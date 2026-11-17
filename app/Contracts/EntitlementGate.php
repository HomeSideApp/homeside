<?php

namespace App\Contracts;

use App\Exceptions\EntitlementQuotaExceededException;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Contract for the app's entitlement gate.
 *
 * The app depends on this port only: when the homeside/saas package is
 * installed the container binds its AppEntitlementGate adapter, otherwise it
 * binds App\Support\NullEntitlementGate and every check passes so a
 * self-hosted install runs unlocked.
 */
interface EntitlementGate
{
    /**
     * Assert the feature is enabled for the user's plan.
     *
     * @throws HttpExceptionInterface When the feature is not enabled.
     */
    public function assertFeature(User $user, string $feature): void;

    /**
     * The user's effective feature flags, as the same resolver assertFeature evaluates.
     *
     * This is a read-only projection of the plan, meant for surfaces that must not
     * advertise what assertFeature would deny (navigation, for instance). A null
     * answer means no entitlement system applies at all -- the self-hosted case --
     * so nothing may be filtered; a non-null map is authoritative for every key it
     * is asked about, and a missing key means "not entitled".
     *
     * @return array<string, bool>|null Effective feature flags, or null when no entitlement system applies
     */
    public function features(User $user): ?array;

    /**
     * Reserve usage against the user's quota for a metered metric.
     *
     * The returned value is the canonical operation id and the ONLY value
     * valid for commitUsage() and releaseUsage(). The caller-supplied
     * $operationId is an idempotency hint, not a handle: an implementation may
     * scope the stored id to the reserving user, so the raw caller id may match
     * no reservation at all and must never be handed back to commit or release.
     *
     * @param  string  $operationId  Caller-chosen stable identifier linking the reservation to its later commit or release. An idempotency hint only, never a handle.
     * @return string The canonical operation id to hand back to commitUsage or releaseUsage.
     *
     * @throws EntitlementQuotaExceededException When the reservation would exceed the quota.
     */
    public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string;

    /**
     * Commit a previously reserved usage so it counts against the quota.
     *
     * @throws EntitlementQuotaExceededException When no matching reservation exists.
     */
    public function commitUsage(string $operationId): void;

    /**
     * Release a previously reserved usage without counting it.
     *
     * @throws EntitlementQuotaExceededException When no matching reservation exists.
     */
    public function releaseUsage(string $operationId): void;

    /**
     * Assert the user's billing account can own the incoming households.
     *
     * @throws EntitlementQuotaExceededException When the ownership quota would be exceeded.
     */
    public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void;

    /**
     * Reserve ticket recognition usage for an economic import.
     *
     * @param  string  $importId  The import identifier the reservation belongs to.
     * @return string The operation id to hand to commitUsage or releaseUsage.
     *
     * @throws EntitlementQuotaExceededException When the recognition quota is exhausted.
     */
    public function reserveTicketRecognition(User $user, string $importId): string;
}
