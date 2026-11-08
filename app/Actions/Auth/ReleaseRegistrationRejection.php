<?php

namespace App\Actions\Auth;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Models\User;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lifts a rejection so the claim can be judged again under the current policy.
 *
 * Releasing is NOT approving. The action lifts the persistent identity block
 * (the address may register again) and returns the account to `pending`, which
 * records "no decision has been made yet". The administrator can then approve
 * it through the normal path, and the current channel policy is what governs
 * any new registration attempt. Releasing silently approving would let a
 * policy change be bypassed by a release.
 *
 * The transition is `rejected` -> `pending`; repeating it is an idempotent
 * no-op, and it is audited as `rejection_released` with the acting
 * administrator. Authorization goes through the policy
 * (`registrations.release-rejection`).
 */
final class ReleaseRegistrationRejection
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Release the target's rejection.
     *
     * @param  User  $target  The rejected account to release.
     * @param  User  $actor  The administrator performing the release.
     * @return bool True when this call lifted a rejection, false when there was none.
     */
    public function execute(User $target, User $actor): bool
    {
        Gate::forUser($actor)->authorize('registrations.release-rejection', $target);

        return DB::transaction(function () use ($target, $actor): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->approval_status !== ApprovalStatus::Rejected->value) {
                return false;
            }

            // Lifts the prohibition only; the account is NOT approved.
            $this->blocks->releaseForIdentifier(
                RegistrationIdentifier::EMAIL,
                $locked->email,
                $actor,
            );

            $locked->forceFill([
                'approval_status' => ApprovalStatus::Pending->value,
            ])->save();

            $this->audit->append(
                RegistrationAuditAction::RejectionReleased,
                $locked,
                $actor,
                ApprovalStatus::Rejected->value,
                ApprovalStatus::Pending->value,
                null,
                'admin',
            );

            return true;
        }, 3);
    }
}
