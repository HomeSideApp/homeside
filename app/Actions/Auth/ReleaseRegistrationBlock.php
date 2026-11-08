<?php

namespace App\Actions\Auth;

use App\Contracts\RegistrationBlockRepository;
use App\Enums\RegistrationAuditAction;
use App\Models\RegistrationBlock;
use App\Models\User;
use App\Support\Registration\RegistrationAuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Releases a persistent identity block.
 *
 * This is the administrative half of the block lifecycle: it lifts the
 * prohibition on an identity that was blocked (a rejection, a manual block or
 * a delete-and-block) without touching the account row, which may no longer
 * exist. Releasing only removes the prohibition; a fresh registration is still
 * subject to the channel policy that is in force at that moment.
 *
 * The block keeps its history (`released_at`/`released_by` are set, the row is
 * never deleted). Repeating the release is an idempotent no-op, and the action
 * is audited as `block_released` with the acting administrator. Authorization
 * uses the dedicated `admin.registration-blocks.release` permission through the
 * `registration-blocks.release` gate.
 */
final class ReleaseRegistrationBlock
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RegistrationAuditRecorder $audit,
    ) {}

    /**
     * Release the given block.
     *
     * @param  RegistrationBlock  $block  The block to release.
     * @param  User  $actor  The administrator performing the release.
     * @return bool True when this call lifted the block, false when it was already released.
     */
    public function execute(RegistrationBlock $block, User $actor): bool
    {
        Gate::forUser($actor)->authorize('registration-blocks.release', $block);

        return DB::transaction(function () use ($block, $actor): bool {
            $locked = RegistrationBlock::query()->lockForUpdate()->findOrFail($block->id);

            if (! $locked->isActive()) {
                return false;
            }

            $this->blocks->release($locked, $actor);

            $this->audit->append(
                RegistrationAuditAction::BlockReleased,
                null,
                $actor,
                null,
                null,
                null,
                'admin',
            );

            return true;
        }, 3);
    }
}
