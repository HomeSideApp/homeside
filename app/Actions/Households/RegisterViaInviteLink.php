<?php

namespace App\Actions\Households;

use App\Actions\Auth\RegisterAccount;
use App\Data\Auth\InvitationContextData;
use App\Data\Auth\RegisterAccountData;
use App\Data\Auth\RegistrationOutcomeData;
use App\Data\Households\RegisterViaInviteLinkData;
use App\Enums\AppLocale;
use App\Enums\HouseholdAccessProfile;
use App\Enums\RegistrationChannel;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Services\Contacts\ContactResolutionService;
use Illuminate\Support\Facades\DB;

/**
 * Registers a new user through a household invite link, joining the household
 * and consuming one link use.
 *
 * The account is created by the shared RegisterAccount use case, so the block
 * check, the channel policy and the "never overwrite an existing account" rule
 * are enforced in exactly one place. This action owns only what is specific to
 * a household link: the membership row, the active-household convenience and
 * the address-book sync.
 *
 * Atomicity:
 *  - the link row is locked FOR UPDATE before it is validated and consumed, so
 *    two simultaneous acceptances of the last available use can never both
 *    succeed;
 *  - the user, the external identity, the membership and the use counter all
 *    live inside ONE transaction, so an exception cannot leave a half-joined
 *    member behind;
 *  - notifications are dispatched by this action only after that transaction
 *    has committed.
 */
final class RegisterViaInviteLink
{
    public function __construct(
        private ContactResolutionService $contacts,
        private RegisterAccount $accounts,
    ) {}

    /**
     * Execute the invite-link registration.
     *
     * @param  RegisterViaInviteLinkData  $data  The registration data.
     * @param  HouseholdInviteLink  $link  The invite link used to register.
     * @param  string  $origin  The transport origin, e.g. `web` or `api`.
     * @return RegistrationOutcomeData The typed outcome.
     */
    public function execute(RegisterViaInviteLinkData $data, HouseholdInviteLink $link, string $origin = 'web'): RegistrationOutcomeData
    {
        $outcome = DB::transaction(function () use ($data, $link, $origin): RegistrationOutcomeData {
            /** @var HouseholdInviteLink $locked */
            $locked = HouseholdInviteLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isUsable(), 422, __('app.errors.invite_link_unusable'));

            /** @var Household $household */
            $household = $locked->household()->firstOrFail();

            $result = $this->accounts->execute(new RegisterAccountData(
                name: $data->name,
                email: $data->email,
                password: $data->password,
                locale: AppLocale::default()->value,
                channel: RegistrationChannel::HouseholdInvitation,
                invitation: new InvitationContextData(reference: $locked->id),
                origin: $origin,
                denyHouseholdCreation: true,
            ), notify: false);

            // An existing identity, a blocked identity or a denied policy never
            // joins the household and never consumes a use.
            if (! $result->registered() || $result->user === null) {
                return $result;
            }

            $user = $result->user;

            $household->members()->create([
                'user_id' => $user->id,
                'role' => ($locked->access_profile ?? HouseholdAccessProfile::Member)->role(),
                ...HouseholdAccessProfile::attributes(['access_profile' => $locked->access_profile?->value, 'module_access' => $locked->module_access]),
                'joined_at' => now(),
                'contact_id' => $this->contacts->findInMemberBooks($household, $user->email)?->id,
            ]);

            if (! $user->active_household_id) {
                $user->update(['active_household_id' => $locked->household_id]);
            }

            // Keep the link creator's address book in sync with the new member
            $this->contacts->findOrCreateByEmail($locked->creator, $user->email);

            $locked->increment('uses_count');

            return $result;
        });

        $this->accounts->dispatchNotifications($outcome, RegistrationChannel::HouseholdInvitation);

        return $outcome;
    }
}
