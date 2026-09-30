<?php

namespace App\Services\Contacts;

use App\Actions\Contacts\SaveLocalContact;
use App\Data\Contacts\ContactData;
use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves address-book contacts for users, household members and invitations.
 */
final class ContactResolutionService
{
    public function __construct(private SaveLocalContact $saveContact) {}

    /**
     * Find an owner's contact matching the given email address.
     *
     * @param  User  $owner  The user whose private address book is searched
     * @param  string  $email  The email address to match (case-insensitive)
     * @return Contact|null The matched contact, or null when missing
     */
    public function findByEmail(User $owner, string $email): ?Contact
    {
        return Contact::query()
            ->where('user_id', $owner->id)
            ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query
                ->selectRaw('1')
                ->from('contact_emails')
                ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
                ->whereColumn('contact_records.contact_id', 'contacts.id')
                ->whereNull('contact_records.remote_deleted_at')
                ->whereRaw('lower(contact_emails.value) = ?', [mb_strtolower($email)]))
            ->orderBy('display_name')
            ->first();
    }

    /**
     * Find or create a private contact for the owner holding the given email.
     *
     * @param  User  $owner  The user owning the address book
     * @param  string  $email  The email address of the contact
     * @return Contact The existing or newly created contact
     */
    public function findOrCreateByEmail(User $owner, string $email): Contact
    {
        return $this->findByEmail($owner, $email) ?? $this->saveContact->execute(
            ContactData::fromArray([
                'type' => 'person',
                'display_name' => $email,
                'emails' => [['value' => $email, 'type' => 'home', 'preferred' => true]],
            ]),
            $owner,
        );
    }

    /**
     * Find a contact matching the email in any member's address book of the household.
     *
     * @param  Household  $household  The household whose members are searched
     * @param  string  $email  The email address to match (case-insensitive)
     * @return Contact|null The first matching contact, or null
     */
    public function findInMemberBooks(Household $household, string $email): ?Contact
    {
        return Contact::query()
            ->whereIn('user_id', $household->members()->select('user_id'))
            ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query
                ->selectRaw('1')
                ->from('contact_emails')
                ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
                ->whereColumn('contact_records.contact_id', 'contacts.id')
                ->whereNull('contact_records.remote_deleted_at')
                ->whereRaw('lower(contact_emails.value) = ?', [mb_strtolower($email)]))
            ->orderBy('display_name')
            ->first();
    }

    /**
     * Resolve the observer's contact matching a household member's user email.
     *
     * @param  HouseholdMember  $member  The household member being displayed
     * @param  User  $observer  The authenticated user viewing the member
     * @return Contact|null The observer's contact, or null when unmatched
     */
    public function forHouseholdMember(HouseholdMember $member, User $observer): ?Contact
    {
        $email = $member->user?->email;

        return $email === null ? null : $this->forObserverEmail($observer, $email);
    }

    /**
     * Resolve the observer's visible contact matching an email address.
     *
     * @param  User  $observer  The authenticated user whose book is searched
     * @param  string  $email  The email address to match (case-insensitive)
     * @return Contact|null The matched contact, or null when unmatched
     */
    public function forObserverEmail(User $observer, string $email): ?Contact
    {
        return Contact::query()->visibleTo($observer)
            ->whereExists(fn (QueryBuilder $query): QueryBuilder => $query
                ->selectRaw('1')
                ->from('contact_emails')
                ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
                ->whereColumn('contact_records.contact_id', 'contacts.id')
                ->whereNull('contact_records.remote_deleted_at')
                ->whereRaw('lower(contact_emails.value) = ?', [mb_strtolower($email)]))
            ->orderBy('display_name')
            ->first();
    }

    /**
     * Build the public summary of a contact for member listings.
     *
     * @param  Contact|null  $contact  The contact to summarize
     * @return array{display_name: string, avatar_url: string|null, email: string|null, phone: string|null, labels: list<array{id: string, name: string}>}|null Null when no contact
     */
    public function summary(?Contact $contact): ?array
    {
        if ($contact === null) {
            return null;
        }

        return [
            'display_name' => $contact->display_name,
            'avatar_url' => $contact->preferred_photo_record_id ? route('contacts.avatar', $contact->id) : null,
            'email' => $this->primaryValue('contact_emails', $contact->id),
            'phone' => $this->primaryValue('contact_phones', $contact->id),
            'labels' => $contact->labels()
                ->orderBy('name')
                ->get(['contact_labels.id', 'contact_labels.name'])
                ->map(fn (object $label): array => ['id' => (string) $label->id, 'name' => (string) $label->name])
                ->values()
                ->all(),
        ];
    }

    /**
     * Resolve the preferred value (email or phone) of a contact.
     *
     * @param  string  $table  The value table name (contact_emails or contact_phones)
     * @param  string  $contactId  The contact identifier
     * @return string|null The preferred value, or null when the contact has none
     */
    private function primaryValue(string $table, string $contactId): ?string
    {
        return DB::table($table)
            ->join('contact_records', 'contact_records.id', '=', $table.'.contact_record_id')
            ->where('contact_records.contact_id', $contactId)
            ->whereNull('contact_records.remote_deleted_at')
            ->where(fn (QueryBuilder $values): QueryBuilder => $values
                ->whereNull('contact_records.contact_source_id')
                ->orWhereNotExists(fn (QueryBuilder $local): QueryBuilder => $local
                    ->selectRaw('1')
                    ->from('contact_records as local_records')
                    ->where('local_records.contact_id', $contactId)
                    ->whereNull('local_records.contact_source_id')
                    ->whereNull('local_records.remote_deleted_at')))
            ->orderByRaw('CASE WHEN contact_records.contact_source_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('contact_records.source_order')
            ->orderBy('contact_records.created_at')
            ->orderBy('contact_records.id')
            ->orderByDesc('preferred')
            ->orderBy($table.'.id')
            ->value('value');
    }
}
