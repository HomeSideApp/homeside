<?php

namespace App\Actions\Auth;

use App\Contracts\RegistrationBlockRepository;
use App\Contracts\RegistrationGate;
use App\Data\Auth\ExternalIdentityData;
use App\Data\Auth\RegisterAccountData;
use App\Data\Auth\RegistrationOutcomeData;
use App\Data\Registration\RegistrationDecisionData;
use App\Enums\AccountStatus;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationAuditAction;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use App\Support\Registration\RegistrationAuditRecorder;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single place that creates a user from a registration.
 *
 * Every channel funnels through this use case, so the fixed check order, the
 * atomicity rules and the "never take over an existing account" rule are
 * enforced once:
 *
 *  1. normalise identity and channel;
 *  2. refuse a blocked identity FIRST, on every channel (an invitation never
 *     bypasses a block);
 *  3. resolve an existing account or a targeted invitation;
 *  4. evaluate the registration gate for the channel policy;
 *  5. create the user and its external identity inside ONE transaction;
 *  6. record the audit entry;
 *  7. let ResolveRegistrationNextStep decide the follow-up step;
 *  8. dispatch notifications only after the transaction returns.
 *
 * It is deliberately HTTP-free: it never returns a status code, a redirect, an
 * Inertia component or a token. The transports own all of that.
 */
final class RegisterAccount
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RegistrationGate $gate,
        private RegistrationAuditRecorder $audit,
        private ResolveRegistrationNextStep $nextStep,
    ) {}

    /**
     * Run the registration use case.
     *
     * The whole decision runs inside one transaction so a failure can never
     * leave a partially registered user behind. When the caller already owns a
     * transaction (for example the household invite-link flow, which must add
     * the membership and consume the link atomically), pass
     * `$notify: false` and dispatch with `dispatchNotifications()` after that
     * outer transaction commits, so no mail is ever sent before commit.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @param  bool  $notify  Whether to dispatch notifications after this transaction.
     * @return RegistrationOutcomeData The typed outcome.
     */
    public function execute(RegisterAccountData $data, bool $notify = true): RegistrationOutcomeData
    {
        $outcome = DB::transaction(fn (): RegistrationOutcomeData => $this->perform($data));

        if ($notify) {
            $this->dispatchNotifications($outcome, $data->channel);
        }

        return $outcome;
    }

    /**
     * Dispatch the after-commit notifications for an outcome.
     *
     * Only a registration that actually created or completed an account, whose
     * email is not already proven, is notified. A blocked, denied, existing or
     * link-required outcome sends nothing.
     *
     * @param  RegistrationOutcomeData  $outcome  The outcome to notify for.
     * @param  RegistrationChannel  $channel  The channel the registration used.
     */
    public function dispatchNotifications(RegistrationOutcomeData $outcome, RegistrationChannel $channel): void
    {
        $user = $outcome->user;

        if ($user === null || ! $outcome->registered() || $user->hasVerifiedEmail()) {
            return;
        }

        if ($channel === RegistrationChannel::Api) {
            $user->notify(new MobileVerifyEmailNotification);

            return;
        }

        $user->sendEmailVerificationNotification();
    }

    /**
     * Perform the registration decision inside the surrounding transaction.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @return RegistrationOutcomeData The typed outcome.
     */
    private function perform(RegisterAccountData $data): RegistrationOutcomeData
    {
        $email = $data->normalisedEmail();

        if ($email === '') {
            return $this->denied('identity_invalid');
        }

        // The block check runs before anything else, on every channel. An
        // invitation must never let a blocked identity back in.
        if ($this->blocks->isBlocked(RegistrationIdentifier::EMAIL, $email)) {
            return $this->denied('identity_blocked');
        }

        if ($data->invitation?->completesExistingAccount() === true) {
            return $this->completeInvitedAccount($data, $email);
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            // An external identity that matches an account by email is NOT
            // linked automatically: the owner must authenticate and link it.
            $decision = $data->externalIdentity !== null
                ? RegistrationDecision::LinkRequired
                : RegistrationDecision::ExistingIdentity;

            return new RegistrationOutcomeData(
                $existing,
                $decision,
                RegistrationOutcomeData::NEXT_AUTHENTICATE,
                false,
                'identity_exists',
            );
        }

        $gate = $this->gate->evaluate($data->channel, $email);

        if (! in_array($gate->decision, [RegistrationDecision::Allowed, RegistrationDecision::ApprovalRequired], true)) {
            return new RegistrationOutcomeData(
                null,
                $gate->decision,
                RegistrationOutcomeData::NEXT_NONE,
                false,
                $gate->reason ?? 'registration_denied',
            );
        }

        return $this->create($data, $email, $gate);
    }

    /**
     * Complete a targeted invitation's pre-created account instead of creating one.
     *
     * This is the only path that may write a password over an existing row, and
     * it is reachable only through the signed, address-bound invitation link:
     * the target must match the normalised email exactly, and an already
     * completed account is never touched again.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @param  string  $email  The normalised email.
     * @return RegistrationOutcomeData The typed outcome.
     */
    private function completeInvitedAccount(RegisterAccountData $data, string $email): RegistrationOutcomeData
    {
        $user = User::query()->lockForUpdate()->find($data->invitation?->targetUserId);

        if ($user === null || mb_strtolower($user->email) !== $email) {
            return $this->denied('invitation_target_mismatch');
        }

        if ($user->email_verified_at !== null) {
            return new RegistrationOutcomeData(
                $user,
                RegistrationDecision::ExistingIdentity,
                RegistrationOutcomeData::NEXT_AUTHENTICATE,
                false,
                'identity_exists',
            );
        }

        $user->forceFill([
            'name' => $this->resolveName($data, $email),
            'password' => $data->password ?? Str::random(80),
            'email_verified_at' => $data->invitation?->emailVerified === true ? now() : $user->email_verified_at,
        ])->save();

        $this->audit->append(
            RegistrationAuditAction::RegistrationCreated,
            $user,
            null,
            null,
            $user->approval_status,
            $data->channel->value,
            $data->origin,
            $data->invitation?->reference,
        );

        return $this->nextStep->resolve($user, RegistrationDecision::Allowed, 'invitation_completed');
    }

    /**
     * Create the new account, its external identity and its audit entry.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @param  string  $email  The normalised email.
     * @param  RegistrationDecisionData  $gate  The gate decision that allowed the registration.
     * @return RegistrationOutcomeData The typed outcome.
     */
    private function create(RegisterAccountData $data, string $email, RegistrationDecisionData $gate): RegistrationOutcomeData
    {
        $requiresApproval = $gate->decision === RegistrationDecision::ApprovalRequired;
        $verified = $this->identityIsVerified($data);

        $attributes = [
            'name' => $this->resolveName($data, $email),
            'email' => $email,
            'password' => $data->password ?? Str::random(80),
            'locale' => $data->locale,
            'email_verified_at' => $verified ? now() : null,
            'approval_status' => $requiresApproval ? ApprovalStatus::Pending->value : ApprovalStatus::Approved->value,
            'account_status' => AccountStatus::Active->value,
        ];

        if ($data->denyHouseholdCreation) {
            $attributes['can_create_households_override'] = false;
        }

        $user = User::create($attributes);

        if ($data->externalIdentity !== null) {
            $this->linkIdentity($user, $data->externalIdentity);
        }

        $this->assignDefaultRole($user, $data->channel, $gate->decision);

        $this->audit->append(
            RegistrationAuditAction::RegistrationCreated,
            $user,
            null,
            null,
            $user->approval_status,
            $data->channel->value,
            $data->origin,
            $data->invitation?->reference,
        );

        return $this->nextStep->resolve($user, $gate->decision);
    }

    /**
     * Determine whether the identity's email was proven during registration.
     *
     * Only a reliable external verification claim or a TARGETED invitation
     * (which proves possession of the address) verifies an email. A generic
     * reusable invite link never does: the registrant typed the address.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @return bool True when the email may be marked verified.
     */
    private function identityIsVerified(RegisterAccountData $data): bool
    {
        if ($data->externalIdentity?->emailVerified === true) {
            return true;
        }

        return $data->invitation !== null
            && $data->invitation->targeted
            && $data->invitation->emailVerified;
    }

    /**
     * Persist an external identity for the new account.
     *
     * @param  User  $user  The new user.
     * @param  ExternalIdentityData  $identity  The verified external identity.
     */
    private function linkIdentity(User $user, ExternalIdentityData $identity): void
    {
        if ($identity->provider === 'google') {
            $user->googleIdentity()->create(['google_sub' => $identity->subject]);
        }
    }

    /**
     * Assign the default `user` role where the channel grants one.
     *
     * Google users wait for an administrator to choose their roles while they
     * are pending, but an `open` Google registration is approved immediately
     * and therefore needs the default role to be usable at all. An invited
     * account already carries the roles chosen explicitly when it was created.
     *
     * @param  User  $user  The new user.
     * @param  RegistrationChannel  $channel  The channel the registration used.
     * @param  RegistrationDecision  $decision  The decision that created the user.
     */
    private function assignDefaultRole(User $user, RegistrationChannel $channel, RegistrationDecision $decision): void
    {
        $grantsDefault = in_array($channel, [RegistrationChannel::Web, RegistrationChannel::Api, RegistrationChannel::HouseholdInvitation], true)
            || ($channel === RegistrationChannel::Google && $decision === RegistrationDecision::Allowed);

        if (! $grantsDefault) {
            return;
        }

        $role = Role::query()->where('slug', 'user')->first();

        if ($role !== null && ! $user->roles()->whereKey($role->id)->exists()) {
            $user->assignRole($role);
        }
    }

    /**
     * Resolve the display name, falling back to the email.
     *
     * @param  RegisterAccountData  $data  The registration input.
     * @param  string  $email  The normalised email.
     * @return string The display name.
     */
    private function resolveName(RegisterAccountData $data, string $email): string
    {
        $name = is_string($data->name) ? trim($data->name) : '';

        return $name !== '' ? $name : $email;
    }

    /**
     * Build a fail-closed denied outcome.
     *
     * @param  string  $reason  The stable machine code.
     * @return RegistrationOutcomeData The denied outcome.
     */
    private function denied(string $reason): RegistrationOutcomeData
    {
        return new RegistrationOutcomeData(
            null,
            RegistrationDecision::Denied,
            RegistrationOutcomeData::NEXT_NONE,
            false,
            $reason,
        );
    }
}
