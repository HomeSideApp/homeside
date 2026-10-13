<?php

namespace App\Actions\Auth;

use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Models\Role;
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
 *
 * Approval also has to make the account USABLE. A Google account is created
 * with `approval_status = pending` and NO role, because the administrator is
 * the one who chooses the roles; approving it used to change only the status,
 * leaving `canAccessApplication()` to refuse it (a Google-linked account with
 * no role is denied) with nothing on screen explaining why. The action
 * therefore confers the baseline `user` role -- the same role every other
 * channel already receives -- when, and only when, the account holds no role at
 * all. It never grants or removes anything beyond that baseline role, so the
 * separate `admin.users.assign-roles` privilege is untouched: approving does
 * not become a way to escalate privileges, and an administrator who assigned
 * roles before approving keeps exactly those roles.
 */
final class ApproveRegistration
{
    /**
     * The baseline role every usable account must hold.
     */
    private const BASELINE_ROLE = 'user';

    public function __construct(private RegistrationAuditRecorder $audit) {}

    /**
     * Approve the target's registration.
     *
     * @param  User  $target  The account to approve.
     * @param  User  $actor  The administrator performing the approval.
     * @return bool True when this call applied the transition, false when the account was already approved.
     *
     * @throws ValidationException When the account is rejected and must be released first, or when the
     *                             baseline `user` role is missing and the approval cannot produce a usable account.
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

            // Confer the baseline role in the SAME transaction as the status
            // change so approval and usability can never diverge: a committed
            // approval that leaves the account refused by `canAccessApplication`
            // is exactly the gap this closes.
            $conferredRole = $this->conferBaselineRole($locked);

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
                // The audit row is extended to make the conferral visible in
                // the trail: `origin` carries the acting context plus the
                // baseline role this approval granted, if it granted one.
                $conferredRole === null ? 'admin' : 'admin+default-role:'.$conferredRole,
            );

            return true;
        }, 3);

        if ($applied) {
            $this->notify($target);
        }

        return $applied;
    }

    /**
     * Confer the baseline role on an account that holds no role at all.
     *
     * An account that already holds ANY role -- including a privileged one an
     * administrator deliberately assigned before approving -- is returned
     * untouched, so roles are never overwritten or added to. This makes the
     * grant idempotent by construction: once the account holds the baseline
     * role, a repeated approval finds a role and does nothing.
     *
     * @param  User  $locked  The account being approved, locked for update.
     * @return string|null The slug of the conferred role, or null when the account already had a role.
     *
     * @throws ValidationException When the baseline role is missing: approving would
     *                             produce an unusable account, so the approval is refused.
     */
    private function conferBaselineRole(User $locked): ?string
    {
        if ($locked->roles()->exists()) {
            return null;
        }

        $role = Role::query()->where('slug', self::BASELINE_ROLE)->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'user' => __('app.registrations.errors.default_role_missing'),
            ]);
        }

        $locked->assignRole($role);

        return $role->slug;
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
