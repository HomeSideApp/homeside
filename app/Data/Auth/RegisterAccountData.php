<?php

namespace App\Data\Auth;

use App\Enums\RegistrationChannel;

/**
 * The transport-independent input to the shared registration use case.
 *
 * Every channel funnels through this DTO, so the use case never needs to know
 * whether the caller is a web controller, an API controller or a Google
 * callback. The password is nullable because an external identity registers
 * without one; the use case then stores an unguessable placeholder so the
 * account can never be authenticated by password.
 */
final readonly class RegisterAccountData
{
    /**
     * @param  string|null  $name  The display name, or null to fall back to the email.
     * @param  string  $email  The raw email; the use case normalises it.
     * @param  string|null  $password  The chosen password, or null for an external identity.
     * @param  string  $locale  The preferred locale value.
     * @param  RegistrationChannel  $channel  The channel requesting the registration.
     * @param  InvitationContextData|null  $invitation  The invitation context, when applicable.
     * @param  ExternalIdentityData|null  $externalIdentity  The external identity, when applicable.
     * @param  string|null  $origin  The transport origin, e.g. `web` or `api`.
     * @param  bool  $denyHouseholdCreation  Whether the new user must be forbidden from creating households.
     */
    public function __construct(
        public ?string $name,
        public string $email,
        public ?string $password,
        public string $locale,
        public RegistrationChannel $channel,
        public ?InvitationContextData $invitation = null,
        public ?ExternalIdentityData $externalIdentity = null,
        public ?string $origin = null,
        public bool $denyHouseholdCreation = false,
    ) {}

    /**
     * Normalise the email to the canonical lookup form.
     *
     * @return string The lowercased, trimmed address.
     */
    public function normalisedEmail(): string
    {
        return mb_strtolower(trim($this->email));
    }

    /**
     * Return a copy of the data with the invitation context attached.
     *
     * @param  InvitationContextData  $invitation  The invitation context.
     * @return self The new data.
     */
    public function withInvitation(InvitationContextData $invitation): self
    {
        return new self(
            name: $this->name,
            email: $this->email,
            password: $this->password,
            locale: $this->locale,
            channel: $this->channel,
            invitation: $invitation,
            externalIdentity: $this->externalIdentity,
            origin: $this->origin,
            denyHouseholdCreation: $this->denyHouseholdCreation,
        );
    }
}
