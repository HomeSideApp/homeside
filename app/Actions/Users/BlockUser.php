<?php

namespace App\Actions\Users;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\AccountStatus;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\User;
use App\Notifications\AccountSuspended;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Blocks an account and its identity.
 *
 * Blocking is stronger than suspension on purpose: it sets
 * `account_status` to `blocked` AND persists an identity-level block, so the
 * address cannot simply re-register. That is the difference the spec asks for
 * between the two administrative conditions: a suspension is a state of the
 * account row, a block also survives as a prohibition on the identity.
 *
 * Access is revoked, the last-administrator safeguard is enforced by the policy
 * (`block`), and repeating the action is an idempotent no-op that produces no
 * second block and no duplicate audit row.
 */
final class BlockUser
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Block the target account and identity.
     *
     * @param  User  $target  The account to block.
     * @param  User  $actor  The administrator performing the block.
     * @param  string|null  $reason  The human reason, stored on the identity block.
     * @return bool True when this call applied the transition, false when already blocked.
     */
    public function execute(User $target, User $actor, ?string $reason = null): bool
    {
        Gate::forUser($actor)->authorize('block', $target);

        $applied = DB::transaction(function () use ($target, $actor, $reason): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($target->id);

            if ($locked->account_status === AccountStatus::Blocked) {
                return false;
            }

            $previous = $locked->account_status->value;

            $locked->forceFill(['account_status' => AccountStatus::Blocked->value])->save();

            // Idempotent by construction: a second call returns the existing
            // active block instead of creating another one.
            $this->blocks->block(
                RegistrationIdentifier::EMAIL,
                $locked->email,
                RegistrationBlockReason::ManualBlock,
                RegistrationBlockSource::Admin,
                $actor,
                $reason,
            );

            $this->audit->append(
                RegistrationAuditAction::Blocked,
                $locked,
                $actor,
                $previous,
                AccountStatus::Blocked->value,
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
     * Notify the blocked user without letting a mail failure undo the decision.
     *
     * @param  User  $target  The blocked account.
     */
    private function notify(User $target): void
    {
        try {
            $target->notify(new AccountSuspended(AccountStatus::Blocked));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
