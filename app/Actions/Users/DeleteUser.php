<?php

namespace App\Actions\Users;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationBlockReason;
use App\Enums\RegistrationBlockSource;
use App\Models\User;
use App\Support\PlatformAccess;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an account, optionally blocking the identity as well.
 *
 * Deleting and blocking are DIFFERENT decisions and the caller must choose
 * explicitly:
 *
 *  - `alsoBlock: false` removes the account and leaves the identity free. Under
 *    the current policy the same address may register again, which is the right
 *    outcome for an accidental or duplicate account.
 *  - `alsoBlock: true` removes the account AND persists a permanent identity
 *    block, so the address cannot come back until an administrator releases it.
 *
 * The two halves of the destructive decision — the block and the deletion —
 * happen inside ONE transaction with one audit entry, so there is never an
 * intermediate state where the user is gone but the block is missing, or the
 * block exists but the user remains. A repeat call on an already-deleted
 * account is an idempotent no-op: `alsoBlock` produces no second block and no
 * second audit row.
 *
 * Before deleting, the action resolves the references that must NOT be
 * silently rewritten: household ownership and platform management. It reuses
 * the existing safeguard and refuses with the same validation error when the
 * ownership must be transferred first; it never auto-transfers ownership to an
 * arbitrary user. Memberships, invitations and other references cascade.
 */
final class DeleteUser
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RevokeUserAccess $revoke,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Delete the target account, block its identity when asked.
     *
     * @param  User  $target  The account to delete.
     * @param  User  $actor  The administrator performing the deletion.
     * @param  bool  $alsoBlock  Whether to persist a permanent identity block.
     * @param  string|null  $reason  The human reason, stored on the identity block.
     *
     * @throws ValidationException When ownership or the last administrator blocks the deletion.
     */
    public function execute(User $target, User $actor, bool $alsoBlock, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize($alsoBlock ? 'delete-and-block' : 'delete', $target);

        DB::transaction(function () use ($target, $actor, $alsoBlock, $reason): void {
            $locked = User::query()->lockForUpdate()->find($target->id);

            // Already deleted: idempotent no-op instead of a second audit row.
            if ($locked === null) {
                return;
            }

            // Refuse before any write when the account still owns a household
            // or is the last administrator. Never auto-transfer ownership.
            app(PlatformAccess::class)->ensureCanDelete($locked);

            // Revoke first so no credential outlives the account row.
            $this->revoke->execute($locked);

            if ($alsoBlock) {
                $this->blocks->block(
                    RegistrationIdentifier::EMAIL,
                    $locked->email,
                    RegistrationBlockReason::DeletedAndBlocked,
                    RegistrationBlockSource::Admin,
                    $actor,
                    $reason,
                );
            }

            $this->audit->append(
                $alsoBlock ? RegistrationAuditAction::DeletedAndBlocked : RegistrationAuditAction::Deleted,
                $locked,
                $actor,
                $locked->account_status->value,
                'deleted',
                null,
                'admin',
            );

            $locked->delete();
        }, 3);
    }
}
