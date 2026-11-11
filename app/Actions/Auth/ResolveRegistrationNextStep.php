<?php

namespace App\Actions\Auth;

use App\Data\Auth\RegistrationOutcomeData;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationDecision;
use App\Models\User;

/**
 * Decides what the client must do next after a registration resolves.
 *
 * Keeping this in one action means every HTTP adapter — web form, JSON API,
 * Google callback — answers with the same vocabulary, and adding a step later
 * touches a single place. The action is pure domain: it returns a typed
 * outcome and never a redirect, a status code or a token.
 *
 * The order is deliberate and fail-closed:
 *  1. a denied or non-creating decision never establishes a session;
 *  2. a pending account waits for approval;
 *  3. an unverified email must be verified;
 *  4. an account without two-factor must enrol before it can be signed in
 *     (unless the caller explicitly delegates that factor to its identity
 *     provider, which is the caller's decision, never a guess made here);
 *  5. only then is the account authenticated.
 */
final class ResolveRegistrationNextStep
{
    /**
     * Resolve the next step for a user and the decision that produced them.
     *
     * @param  User  $user  The created, completed or matched user.
     * @param  RegistrationDecision  $decision  The decision reached by the use case.
     * @param  string|null  $reason  An optional stable machine code.
     * @param  bool  $requireTwoFactorEnrolment  Whether an account without two-factor must enrol next. Callers that delegate the second factor to their identity provider pass `false`.
     * @return RegistrationOutcomeData The typed outcome.
     */
    public function resolve(User $user, RegistrationDecision $decision, ?string $reason = null, bool $requireTwoFactorEnrolment = true): RegistrationOutcomeData
    {
        if (! in_array($decision, [RegistrationDecision::Allowed, RegistrationDecision::ApprovalRequired], true)) {
            return new RegistrationOutcomeData($user, $decision, RegistrationOutcomeData::NEXT_AUTHENTICATE, false, $reason);
        }

        if ($user->approval_status === ApprovalStatus::Pending->value) {
            return new RegistrationOutcomeData($user, $decision, RegistrationOutcomeData::NEXT_AWAIT_APPROVAL, false, $reason);
        }

        if (! $user->hasVerifiedEmail()) {
            return new RegistrationOutcomeData($user, $decision, RegistrationOutcomeData::NEXT_VERIFY_EMAIL, false, $reason);
        }

        if ($requireTwoFactorEnrolment && ! $user->hasEnabledTwoFactorAuthentication()) {
            return new RegistrationOutcomeData($user, $decision, RegistrationOutcomeData::NEXT_ENROL_TWO_FACTOR, false, $reason);
        }

        return new RegistrationOutcomeData($user, $decision, RegistrationOutcomeData::NEXT_AUTHENTICATED, true, $reason);
    }
}
