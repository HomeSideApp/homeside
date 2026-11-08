<?php

namespace App\Actions\Auth;

use App\Actions\Users\RevokeUserAccess;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\User;
use App\Notifications\RegistrationRejected;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Rejects a registration and forbids the identity from coming back.
 *
 * The legal transitions are explicit:
 *
 *  - `pending`  -> `rejected` (sets the approval status AND creates the
 *    persistent identity block in the SAME transaction);
 *  - `rejected` -> `rejected` (no-op: no duplicate audit row, no second block);
 *  - `approved` -> refused. Removing an approved account is a suspension or a
 *    deletion, not a rejection; conflating them would leave a rejected user who
 *    was already admitted.
 *
 * The block is what makes a rejection stick: the address cannot re-register on
 * any channel (web, API, Google, invitation) until an administrator releases
 * it. Access is also revoked, so any session that existed at rejection time
 * dies immediately. Authorization goes through the policy
 * (`registrations.reject`), and the notification is dispatched only after the
 * transaction commits.
 */
final class RejectRegistration
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Reject the target's registration.
     *
     * @param  User  $target  The account to reject.
     * @param  User  $actor  The administrator performing the rejection.
     * @param  string|null  $reason  The human reason, stored on the block only.
     * @return bool True when this call applied the transition, false when already rejected.
     *
     * @throws ValidationException When the account is already approved.
     */
    public function execute(User $target, User $actor, ?string $reason = null): bool
    {
        Gate::forUser($actor)->authorize('registrations.reject', $target);

        $applied = DB::transaction(function () use ($target, $actor, $reason): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->approval_status === ApprovalStatus::Rejected->value) {
                return false;
            }

            if ($locked->approval_status === ApprovalStatus::Approved->value) {
                throw ValidationException::withMessages([
                    'user' => __('app.registrations.errors.approved_cannot_be_rejected'),
                ]);
            }

            $previous = $locked->approval_status;

            $locked->forceFill([
                'approval_status' => ApprovalStatus::Rejected->value,
                'approved_at' => null,
            ])->save();

            // The identity block is created in the same transaction as the
            // status change, so the two can never be observed apart.
            $this->blocks->block(
                RegistrationIdentifier::EMAIL,
                $locked->email,
                RegistrationBlockReason::RejectedRegistration,
                RegistrationBlockSource::Admin,
                $actor,
                $reason,
            );

            $this->audit->append(
                RegistrationAuditAction::Rejected,
                $locked,
                $actor,
                $previous,
                ApprovalStatus::Rejected->value,
                null,
                'admin',
            );

            return true;
        }, 3);

        // Revocation and notification are best-effort side effects: the decision
        // is already persisted and must never be undone by a failure here. A
        // repeat rejection is still revoked but is NOT re-notified, so the
        // person receives one message per real decision.
        $this->revoke->execute($target);

        if ($applied) {
            $this->notify($target);
        }

        return $applied;
    }

    /**
     * Notify the rejected user without disclosing the administrative reasoning.
     *
     * @param  User  $target  The rejected account.
     */
    private function notify(User $target): void
    {
        try {
            $target->notify(new RegistrationRejected);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
