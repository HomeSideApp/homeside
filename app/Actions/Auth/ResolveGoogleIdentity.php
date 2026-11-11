<?php

namespace App\Actions\Auth;

use App\Contracts\RegistrationBlockRepository;
use App\Data\Auth\ExternalIdentityData;
use App\Data\Auth\RegisterAccountData;
use App\Data\Auth\RegistrationOutcomeData;
use App\Enums\AppLocale;
use App\Enums\ApprovalStatus;
use App\Enums\RegistrationChannel;
use App\Enums\RegistrationDecision;
use App\Models\GoogleIdentity;
use App\Models\User;
use App\Notifications\GoogleAccessRequested;
use App\Support\Auth\GoogleSecondFactorPolicy;
use App\Support\Registration\RegistrationIdentifier;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a Google sign-in against the shared registration use case.
 *
 * The resolution order is fixed and never links by email match:
 *
 *  1. the identity is already linked  -> sign the existing user in (subject to
 *     approval and account status);
 *  2. the email matches an account that is NOT linked -> `link_required`; the
 *     owner must authenticate and link it explicitly;
 *  3. a brand-new identity -> evaluate the registration gate and create the
 *     account through RegisterAccount, so the block check and the policy run in
 *     exactly one place.
 *
 * The provider's `email_verified` claim is trusted only when the transport
 * actually received a reliable assertion; an unproven email is refused outright
 * because the whole flow relies on that address identifying a person.
 */
final class ResolveGoogleIdentity
{
    public function __construct(
        private RegistrationBlockRepository $blocks,
        private RegisterAccount $accounts,
        private ResolveRegistrationNextStep $nextStep,
    ) {}

    /**
     * Resolve a Google identity into a registration outcome.
     *
     * @param  string  $subject  The provider's stable subject identifier.
     * @param  string  $email  The provider-asserted email.
     * @param  string  $name  The provider-asserted display name.
     * @param  bool  $emailVerified  Whether the provider reliably verified the email.
     * @return RegistrationOutcomeData The typed outcome.
     *
     * @throws ValidationException When the provider did not reliably verify the email.
     */
    public function resolve(string $subject, string $email, string $name, bool $emailVerified): RegistrationOutcomeData
    {
        $normalised = mb_strtolower(trim($email));

        if ($subject === '' || $normalised === '' || ! $emailVerified) {
            throw ValidationException::withMessages(['google' => 'Google no ha verificado esta dirección de correo.']);
        }

        $identity = GoogleIdentity::query()->with('user')->where('google_sub', $subject)->first();
        $linked = $identity?->user;

        if ($linked instanceof User) {
            return $this->resolveExisting($linked);
        }

        if ($this->blocks->isBlocked(RegistrationIdentifier::EMAIL, $normalised)) {
            return new RegistrationOutcomeData(null, RegistrationDecision::Denied, RegistrationOutcomeData::NEXT_NONE, false, 'identity_blocked');
        }

        if (User::query()->where('email', $normalised)->exists()) {
            return new RegistrationOutcomeData(null, RegistrationDecision::LinkRequired, RegistrationOutcomeData::NEXT_AUTHENTICATE, false, 'identity_exists');
        }

        $outcome = $this->accounts->execute(new RegisterAccountData(
            name: $name,
            email: $normalised,
            password: null,
            locale: AppLocale::default()->value,
            channel: RegistrationChannel::Google,
            externalIdentity: new ExternalIdentityData('google', $subject, $normalised, true),
            origin: 'web',
        ));

        if ($outcome->decision === RegistrationDecision::ApprovalRequired && $outcome->user !== null) {
            $this->notifyAdministrators($outcome->user);
        }

        // RegisterAccount resolves the follow-up step for every channel, so it
        // can only assume the local second factor is mandatory. When Google is
        // the second factor, a brand-new account is not forced to enrol one —
        // the delegation is applied here, at the caller, never guessed inside
        // the shared action.
        if ($outcome->user !== null && $outcome->nextStep === RegistrationOutcomeData::NEXT_ENROL_TWO_FACTOR) {
            $outcome = $this->resolveNextStep($outcome->user, $outcome->decision, $outcome->reason);
        }

        return $outcome;
    }

    /**
     * Resolve an already-linked identity to its next authentication step.
     *
     * @param  User  $user  The linked user.
     * @return RegistrationOutcomeData The typed outcome.
     */
    private function resolveExisting(User $user): RegistrationOutcomeData
    {
        if ($this->blocks->isBlocked(RegistrationIdentifier::EMAIL, mb_strtolower($user->email))) {
            return new RegistrationOutcomeData(null, RegistrationDecision::Denied, RegistrationOutcomeData::NEXT_NONE, false, 'identity_blocked');
        }

        if ($user->approval_status === ApprovalStatus::Rejected->value) {
            return new RegistrationOutcomeData($user, RegistrationDecision::Denied, RegistrationOutcomeData::NEXT_AUTHENTICATE, false, 'approval_rejected');
        }

        return $this->resolveNextStep($user, RegistrationDecision::Allowed, 'existing_identity');
    }

    /**
     * Resolve the next authentication step under the Google second factor policy.
     *
     * The delegation flag is evaluated here rather than inside the shared action,
     * so `ResolveRegistrationNextStep` keeps answering the same way for every
     * channel and never has to know who the identity provider was.
     *
     * @param  User  $user  The account being signed in.
     * @param  RegistrationDecision  $decision  The decision that produced it.
     * @param  string|null  $reason  An optional stable machine code.
     * @return RegistrationOutcomeData The typed outcome.
     */
    private function resolveNextStep(User $user, RegistrationDecision $decision, ?string $reason = null): RegistrationOutcomeData
    {
        return $this->nextStep->resolve(
            $user,
            $decision,
            $reason,
            requireTwoFactorEnrolment: GoogleSecondFactorPolicy::requiresApplicationOtp(),
        );
    }

    /**
     * Ask the platform administrators to review a new Google registration.
     *
     * @param  User  $user  The pending user.
     */
    private function notifyAdministrators(User $user): void
    {
        try {
            Notification::send(
                User::query()
                    ->where('approval_status', ApprovalStatus::Approved->value)
                    ->whereHas('roles.permissions', fn ($query) => $query->where('route_name', 'admin.users.assign-roles'))
                    ->get(),
                new GoogleAccessRequested($user),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
