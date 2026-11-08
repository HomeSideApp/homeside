<?php

namespace App\Actions\Users;

use App\Enums\AccountStatus;
use App\Enums\RegistrationAuditAction;
use App\Models\User;
use App\Support\Registration\RegistrationAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lifts a suspension.
 *
 * The account returns to `active`; the identity was never blocked, so there is
 * no persistent prohibition to clear. Access is revoked anyway, so the restored
 * account re-authenticates instead of resuming a session that was created
 * before the suspension. Repeating the action is an idempotent no-op.
 *
 * Authorization goes through the policy (`unsuspend`), which shares the
 * `admin.users.suspend` permission with suspension: whoever may suspend may
 * also restore.
 */
final class UnsuspendUser
{
    public function __construct(
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Unsuspend the target account.
     *
     * @param  User  $target  The account to restore.
     * @param  User  $actor  The administrator performing the restoration.
     * @return bool True when this call applied the transition, false when not suspended.
     */
    public function execute(User $target, User $actor): bool
    {
        Gate::forUser($actor)->authorize('unsuspend', $target);

        $applied = DB::transaction(function () use ($target, $actor): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->account_status !== AccountStatus::Suspended) {
                return false;
            }

            $previous = $locked->account_status->value;

            $locked->forceFill(['account_status' => AccountStatus::Active->value])->save();

            $this->audit->append(
                RegistrationAuditAction::Unsuspended,
                $locked,
                $actor,
                $previous,
                AccountStatus::Active->value,
                null,
                'admin',
            );

            return true;
        }, 3);

        $this->revoke->execute($target);

        return $applied;
    }
}
