<?php

namespace App\Actions\Users;

use App\Enums\AccountStatus;
use App\Enums\RegistrationAuditAction;
use App\Models\User;
use App\Notifications\AccountSuspended;
use App\Support\Registration\RegistrationAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Suspends an account.
 *
 * Suspension clears the account's ability to operate WITHOUT erasing the
 * identity: the account row stays, `account_status` becomes `suspended`, and
 * every credential is revoked. The last-administrator safeguard lives in the
 * policy, so an actor can never suspend the platform's only administrator
 * (including themselves). Repeating a suspension is an idempotent no-op, yet
 * still revokes, so any credential issued between the two calls dies too.
 *
 * Authorization goes through the user policy (`suspend`), never a raw
 * permission string.
 */
final class SuspendUser
{
    public function __construct(
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Suspend the target account.
     *
     * @param  User  $target  The account to suspend.
     * @param  User  $actor  The administrator performing the suspension.
     * @return bool True when this call applied the transition, false when already suspended.
     */
    public function execute(User $target, User $actor): bool
    {
        Gate::forUser($actor)->authorize('suspend', $target);

        $applied = DB::transaction(function () use ($target, $actor): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->account_status === AccountStatus::Suspended) {
                return false;
            }

            $previous = $locked->account_status->value;

            $locked->forceFill(['account_status' => AccountStatus::Suspended->value])->save();

            $this->audit->append(
                RegistrationAuditAction::Suspended,
                $locked,
                $actor,
                $previous,
                AccountStatus::Suspended->value,
                null,
                'admin',
            );

            return true;
        }, 3);

        $this->revoke->execute($target);

        if ($applied) {
            $this->notify($target);
        }

        return $applied;
    }

    /**
     * Notify the suspended user without letting a mail failure undo the decision.
     *
     * @param  User  $target  The suspended account.
     */
    private function notify(User $target): void
    {
        try {
            $target->notify(new AccountSuspended(AccountStatus::Suspended));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
