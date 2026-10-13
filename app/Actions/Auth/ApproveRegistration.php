<?php

namespace App\Actions\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Support\Registration\RegistrationAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Approves a pending registration.
 *
 * The legal transitions are explicit:
 *
 *  - `pending`  -> `approved` (the decision; idempotent once approved);
 *  - `approved` -> `approved` (no-op, no duplicate audit row);
 *  - `rejected` -> refused. Approving a rejected account requires releasing the
 *    rejection first, which is a separate audited decision. Allowing the direct
 *    jump would let an approval silently erase a rejection and its block.
 *
 * Authorization goes through the registration-approval POLICY (`registrations
 * .approve`), never a raw permission string. The notification is dispatched
 * only after the transaction commits, so a mail failure leaves the decision
 * persisted and retryable on its own.
 */
final class ApproveRegistration
{
    public function __construct(private RegistrationAuditRecorder $audit) {}

    /**
     * Approve the target's registration.
     *
     * @param  User  $target  The account to approve.
     * @param  User  $actor  The administrator performing the approval.
     * @return bool True when this call applied the transition, false when the account was already approved.
     *
     * @throws ValidationException When the account is rejected and must be released first.
     */
    public function execute(User $target, User $actor): bool
    {
        Gate::forUser($actor)->authorize('registrations.approve', $target);

        $applied = DB::transaction(function () use ($target, $actor): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->approval_status === ApprovalStatus::Approved->value) {
                return false;
            }

            if ($locked->approval_status === ApprovalStatus::Rejected->value) {
                throw ValidationException::withMessages([
                    'user' => __('app.registrations.errors.rejection_must_be_released'),
                ]);
            }

            $previous = $locked->approval_status;

            $locked->forceFill([
                'approval_status' => ApprovalStatus::Approved->value,
                'approved_at' => now(),
            ])->save();

            $this->audit->append(
                RegistrationAuditAction::Approved,
                $locked,
                $actor,
                $previous,
                ApprovalStatus::Approved->value,
                null,
                'admin',
            );

            return true;
        }, 3);

        if ($applied) {
            $this->notify($target);
        }

        return $applied;
    }

    /**
     * Notify the approved user without letting a mail failure undo the decision.
     *
     * @param  User  $target  The approved account.
     */
    private function notify(User $target): void
    {
        try {
            $target->notify(new RegistrationApproved);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
