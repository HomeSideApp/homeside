<?php

namespace App\Actions\Users;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationBlockReason;
use App\Models\User;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lifts an account block and its matching identity block.
 *
 * Only the identity block created by `admin.users.block` (a manual block) is
 * released here, and only when the account was actually blocked. A block
 * created by a rejection or by a delete-and-block is deliberately left alone:
 * releasing those is a different authority (`admin.registration-blocks.release`
 * or `registrations.release-rejection`), and letting this action clear them
 * would turn `admin.users.unblock` into a privilege escalation.
 *
 * Access is revoked in every case, so the restored account re-authenticates.
 * Repeating the action is an idempotent no-op.
 */
final class UnblockUser
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Unblock the target account and its manual identity block.
     *
     * @param  User  $target  The account to restore.
     * @param  User  $actor  The administrator performing the unblock.
     * @return bool True when this call applied the transition, false when not blocked.
     */
    public function execute(User $target, User $actor): bool
    {
        Gate::forUser($actor)->authorize('unblock', $target);

        $applied = DB::transaction(function () use ($target, $actor): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->account_status !== AccountStatus::Blocked) {
                return false;
            }

            $previous = $locked->account_status->value;

            $locked->forceFill(['account_status' => AccountStatus::Active->value])->save();

            $activeBlock = $this->blocks->activeBlockFor(RegistrationIdentifier::EMAIL, $locked->email);

            if ($activeBlock !== null && $activeBlock->reason_code === RegistrationBlockReason::ManualBlock) {
                $this->blocks->release($activeBlock, $actor);
            }

            $this->audit->append(
                RegistrationAuditAction::Unblocked,
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
