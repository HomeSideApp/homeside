<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Auth\ApproveRegistration;
use App\Actions\Auth\RejectRegistration;
use App\Actions\Auth\ReleaseRegistrationBlock;
use App\Actions\Auth\ReleaseRegistrationRejection;
use App\Actions\Users\BlockUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\SuspendUser;
use App\Actions\Users\UnblockUser;
use App\Actions\Users\UnsuspendUser;
use App\Contracts\RegistrationBlockRepository;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeleteUserRequest;
use App\Http\Requests\Admin\RejectRegistrationRequest;
use App\Http\Requests\Admin\ReleaseRegistrationRequest;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Models\RegistrationAudit;
use App\Models\RegistrationBlock;
use App\Models\User;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "Registration & Access" administration surface.
 *
 * The controller is transport only: it reads the panel data, delegates every
 * decision to a domain action, and flashes the outcome. It never mutates a
 * status, a block or a token itself, so the authorization, idempotency and
 * after-commit rules live in exactly one place per decision.
 *
 * Every mutation is an Inertia request, never a JSON API call, and the rejection
 * reason is validated with Precognition so the operator sees the error before
 * submitting and nothing is written by a precognitive request.
 */
final class RegistrationController extends Controller
{
    /**
     * Show the pending, rejected and blocked queues.
     *
     * @param  Request  $request  The incoming request.
     * @param  RegistrationBlockRepository  $blocks  The block repository.
     * @return Response The HTTP response.
     */
    public function index(Request $request, RegistrationBlockRepository $blocks): Response
    {
        $channel = $this->channelFilter($request);

        return Inertia::render('admin/registrations/Index', [
            'pending' => $this->pending($channel),
            'rejected' => $this->rejected($channel, $blocks),
            'blocks' => $this->blocks(),
            'filters' => [
                'status' => $request->string('status')->toString() ?: ApprovalStatus::Pending->value,
                'channel' => $channel,
            ],
            'options' => [
                'channels' => array_map(
                    fn (RegistrationChannel $case): array => ['value' => $case->value, 'label' => $case->label()],
                    RegistrationChannel::cases(),
                ),
                'statuses' => [
                    ['value' => ApprovalStatus::Pending->value, 'label' => ApprovalStatus::Pending->label()],
                    ['value' => ApprovalStatus::Rejected->value, 'label' => ApprovalStatus::Rejected->label()],
                    ['value' => 'blocked', 'label' => 'Blocked'],
                ],
            ],
        ]);
    }

    /**
     * Approve a pending registration.
     *
     * @param  Request  $request  The incoming request.
     * @param  User  $user  The account to approve.
     * @param  ApproveRegistration  $approve  The approval action.
     * @return RedirectResponse The HTTP response.
     */
    public function approve(Request $request, User $user, ApproveRegistration $approve): RedirectResponse
    {
        $applied = $approve->execute($user, $this->authenticatedUser($request));

        $this->flash($applied ? 'approved' : 'already_approved');

        return back();
    }

    /**
     * Reject a pending registration and block its identity.
     *
     * @param  RejectRegistrationRequest  $request  The validated request.
     * @param  User  $user  The account to reject.
     * @param  RejectRegistration  $reject  The rejection action.
     * @return RedirectResponse The HTTP response.
     */
    public function reject(RejectRegistrationRequest $request, User $user, RejectRegistration $reject): RedirectResponse
    {
        $applied = $reject->execute(
            $user,
            $this->authenticatedUser($request),
            (string) $request->validated('reason'),
        );

        $this->flash($applied ? 'rejected' : 'already_rejected');

        return back();
    }

    /**
     * Release a prior rejection.
     *
     * @param  ReleaseRegistrationRequest  $request  The validated request.
     * @param  User  $user  The rejected account.
     * @param  ReleaseRegistrationRejection  $release  The release action.
     * @return RedirectResponse The HTTP response.
     */
    public function releaseRejection(ReleaseRegistrationRequest $request, User $user, ReleaseRegistrationRejection $release): RedirectResponse
    {
        $applied = $release->execute($user, $this->authenticatedUser($request));

        $this->flash($applied ? 'rejection_released' : 'nothing_to_release');

        return back();
    }

    /**
     * Release a persistent identity block.
     *
     * @param  ReleaseRegistrationRequest  $request  The validated request.
     * @param  RegistrationBlock  $block  The block to release.
     * @param  ReleaseRegistrationBlock  $release  The release action.
     * @return RedirectResponse The HTTP response.
     */
    public function releaseBlock(ReleaseRegistrationRequest $request, RegistrationBlock $block, ReleaseRegistrationBlock $release): RedirectResponse
    {
        $applied = $release->execute($block, $this->authenticatedUser($request));

        $this->flash($applied ? 'block_released' : 'block_already_released');

        return back();
    }

    /**
     * Suspend an account.
     *
     * @param  SuspendUserRequest  $request  The validated request.
     * @param  User  $user  The account to suspend.
     * @param  SuspendUser  $suspend  The suspension action.
     * @return RedirectResponse The HTTP response.
     */
    public function suspend(SuspendUserRequest $request, User $user, SuspendUser $suspend): RedirectResponse
    {
        $applied = $suspend->execute($user, $this->authenticatedUser($request));

        $this->flash($applied ? 'suspended' : 'already_suspended');

        return back();
    }

    /**
     * Lift a suspension.
     *
     * @param  SuspendUserRequest  $request  The validated request.
     * @param  User  $user  The account to restore.
     * @param  UnsuspendUser  $unsuspend  The restoration action.
     * @return RedirectResponse The HTTP response.
     */
    public function unsuspend(SuspendUserRequest $request, User $user, UnsuspendUser $unsuspend): RedirectResponse
    {
        $applied = $unsuspend->execute($user, $this->authenticatedUser($request));

        $this->flash($applied ? 'unsuspended' : 'not_suspended');

        return back();
    }

    /**
     * Block an account and its identity.
     *
     * @param  SuspendUserRequest  $request  The validated request.
     * @param  User  $user  The account to block.
     * @param  BlockUser  $block  The blocking action.
     * @return RedirectResponse The HTTP response.
     */
    public function block(SuspendUserRequest $request, User $user, BlockUser $block): RedirectResponse
    {
        $applied = $block->execute(
            $user,
            $this->authenticatedUser($request),
            $request->validated('reason'),
        );

        $this->flash($applied ? 'blocked' : 'already_blocked');

        return back();
    }

    /**
     * Lift a block.
     *
     * @param  SuspendUserRequest  $request  The validated request.
     * @param  User  $user  The account to restore.
     * @param  UnblockUser  $unblock  The unblock action.
     * @return RedirectResponse The HTTP response.
     */
    public function unblock(SuspendUserRequest $request, User $user, UnblockUser $unblock): RedirectResponse
    {
        $applied = $unblock->execute($user, $this->authenticatedUser($request));

        $this->flash($applied ? 'unblocked' : 'not_blocked');

        return back();
    }

    /**
     * Delete an account, optionally blocking its identity in the same operation.
     *
     * @param  DeleteUserRequest  $request  The validated request.
     * @param  User  $user  The account to delete.
     * @param  DeleteUser  $delete  The deletion action.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(DeleteUserRequest $request, User $user, DeleteUser $delete): RedirectResponse
    {
        $alsoBlock = $request->alsoBlocks();

        $delete->execute(
            $user,
            $this->authenticatedUser($request),
            $alsoBlock,
            $request->validated('reason'),
        );

        $this->flash($alsoBlock ? 'deleted_and_blocked' : 'deleted');

        return back();
    }

    /**
     * Resolve the requested channel filter, ignoring an unknown value.
     *
     * @param  Request  $request  The incoming request.
     * @return string|null The channel value, or null for "all channels".
     */
    private function channelFilter(Request $request): ?string
    {
        $value = $request->string('channel')->toString();

        return RegistrationChannel::tryFrom($value)?->value;
    }

    /**
     * Build the pending queue.
     *
     * @param  string|null  $channel  The channel filter, or null.
     * @return list<array<string, mixed>> The pending registrations.
     */
    private function pending(?string $channel): array
    {
        return User::query()
            ->where('approval_status', ApprovalStatus::Pending->value)
            ->when($channel !== null, fn ($query) => $query->whereIn('users.id', $this->userIdsForChannel($channel)))
            ->orderByDesc('created_at')
            ->limit(100)
            ->select('users.*')
            ->addSelect(['registration_channel' => $this->channelSubquery()])
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'channel' => $user->registration_channel,
                'created_at' => $user->created_at?->toIso8601String(),
                'email_verified' => $user->email_verified_at !== null,
                'can_approve' => true,
            ])
            ->all();
    }

    /**
     * Build the rejected queue with who rejected, when and why.
     *
     * @param  string|null  $channel  The channel filter, or null.
     * @param  RegistrationBlockRepository  $blocks  The block repository.
     * @return list<array<string, mixed>> The rejected registrations.
     */
    private function rejected(?string $channel, RegistrationBlockRepository $blocks): array
    {
        return User::query()
            ->where('approval_status', ApprovalStatus::Rejected->value)
            ->when($channel !== null, fn ($query) => $query->whereIn('users.id', $this->userIdsForChannel($channel)))
            ->orderByDesc('updated_at')
            ->limit(100)
            ->select('users.*')
            ->addSelect(['registration_channel' => $this->channelSubquery()])
            ->with('googleIdentity')
            ->get()
            ->map(function (User $user) use ($blocks): array {
                $block = $blocks->activeBlockFor(RegistrationIdentifier::EMAIL, $user->email);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'channel' => $user->registration_channel,
                    'rejected_at' => $block?->created_at?->toIso8601String(),
                    'reason' => $block?->reason_note,
                    'rejected_by' => $block?->creator?->name,
                ];
            })
            ->all();
    }

    /**
     * Build the active identity-block queue.
     *
     * @return list<array<string, mixed>> The active blocks.
     */
    private function blocks(): array
    {
        return RegistrationBlock::query()
            ->active()
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (RegistrationBlock $block): array => [
                'id' => $block->id,
                'reason_code' => $block->reason_code->value,
                'reason' => $block->reason_note,
                'source' => $block->source->value,
                'source_label' => $block->source->label(),
                'created_at' => $block->created_at?->toIso8601String(),
                'created_by' => $block->creator?->name,
            ])
            ->all();
    }

    /**
     * Build the subquery that resolves a user's most recent registration channel.
     *
     * @return Builder<RegistrationAudit> The subquery.
     */
    private function channelSubquery(): mixed
    {
        return RegistrationAudit::query()
            ->select('channel')
            ->whereColumn('user_id', 'users.id')
            ->where('action', RegistrationAuditAction::RegistrationCreated->value)
            ->latest('created_at')
            ->limit(1);
    }

    /**
     * Build the subquery of user ids that registered on a channel.
     *
     * @param  string  $channel  The channel value.
     * @return Builder<RegistrationAudit> The subquery.
     */
    private function userIdsForChannel(string $channel): mixed
    {
        return RegistrationAudit::query()
            ->select('user_id')
            ->where('action', RegistrationAuditAction::RegistrationCreated->value)
            ->where('channel', $channel);
    }

    /**
     * Flash a translated lifecycle outcome.
     *
     * @param  string  $key  The outcome key under `app.registrations.toasts`.
     */
    private function flash(string $key): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.registrations.toasts.'.$key),
        ]);
    }
}
