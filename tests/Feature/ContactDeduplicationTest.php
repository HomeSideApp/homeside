<?php

namespace Tests\Feature;

use App\Actions\Contacts\ImportExternalContact;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\ContactSource;
use App\Models\EconomicTransaction;
use App\Models\User;
use App\Services\Contacts\Providers\VCardParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_matches_wait_for_confirmation_and_merge_keeps_both_sources_on_later_sync(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $firstSource = $this->source($user, 'Primera fuente');
        $secondSource = $this->source($user, 'Segunda fuente');
        $first = $this->import($firstSource, 'first', 'Ada Primera', 'ada@example.com');
        $second = $this->import($secondSource, 'second', 'Ada Segunda', 'ada@example.com');

        $this->assertDatabaseCount('contacts', 2);
        $this->actingAs($user)->get(route('contacts.index'))
            ->assertInertia(fn ($page) => $page->where('duplicate_count', 1));
        $this->get(route('contacts.duplicates.index'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('contacts/Duplicates')
            ->has('groups', 1)
            ->where('groups.0.primary.id', $first->id)
            ->where('groups.0.duplicates.0.id', $second->id)
            );

        $this->post(route('contacts.duplicates.store'), [
            'groups' => [['primary_id' => $first->id, 'duplicate_ids' => [$second->id]]],
        ])->assertRedirect(route('contacts.duplicates.index'));

        $this->assertSame(1, Contact::query()->count());
        $this->get(route('contacts.index'))
            ->assertInertia(fn ($page) => $page->where('duplicate_count', 0));
        $this->assertSame(2, $first->records()->count());
        $this->assertDatabaseHas('contact_records', ['contact_id' => $first->id, 'contact_source_id' => $firstSource->id, 'source_order' => 0]);
        $this->assertDatabaseHas('contact_records', ['contact_id' => $first->id, 'contact_source_id' => $secondSource->id, 'source_order' => 1]);
        $this->assertSame('Ada Primera', $first->fresh()->display_name);
        $this->get(route('contacts.show', $first))
            ->assertInertia(fn ($page) => $page
                ->has('contact.records', 2)
                ->has('sources', 2)
            );

        $this->import($secondSource, 'second', 'Ada Segunda Actualizada', 'ada@example.com');
        $this->assertSame(1, Contact::query()->count());
        $this->assertSame('Ada Primera', $first->fresh()->display_name);
        $this->assertSame(2, $first->records()->count());

        $secondSource->delete();
        $this->get(route('contacts.show', $first))
            ->assertInertia(fn ($page) => $page->has('sources', 2));
    }

    public function test_multiple_proposals_can_be_confirmed_together_and_references_are_relinked(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contacts = [];
        foreach (['ada@example.com', 'grace@example.com'] as $email) {
            foreach (['A', 'B'] as $suffix) {
                $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => $email.' '.$suffix]);
                $record = $contact->records()->create(['formatted_name' => $contact->display_name]);
                DB::table('contact_emails')->insert(['contact_record_id' => $record->id, 'value' => $email, 'preferred' => true]);
                $contacts[] = $contact;
            }
        }
        $transaction = EconomicTransaction::factory()->create(['created_by' => $user->id]);
        DB::table('economic_transaction_contacts')->insert([
            'economic_transaction_id' => $transaction->id,
            'contact_id' => $contacts[1]->id,
            'role' => 'related',
        ]);
        $label = ContactLabel::factory()->create(['user_id' => $user->id]);
        $contacts[1]->labels()->attach($label->id, ['manual' => true, 'imported' => false]);
        DB::table('contact_record_relations')->insert([
            'contact_record_id' => $contacts[2]->records()->firstOrFail()->id,
            'type' => 'friend',
            'related_contact_id' => $contacts[1]->id,
            'name' => $contacts[1]->display_name,
        ]);

        $this->actingAs($user)->post(route('contacts.duplicates.store'), [
            'groups' => [
                ['primary_id' => $contacts[0]->id, 'duplicate_ids' => [$contacts[1]->id]],
                ['primary_id' => $contacts[2]->id, 'duplicate_ids' => [$contacts[3]->id]],
            ],
        ])->assertRedirect();

        $this->assertSame(2, Contact::query()->count());
        $this->assertSame(2, $contacts[0]->records()->count());
        $this->assertSame(2, $contacts[2]->records()->count());
        $this->assertDatabaseHas('economic_transaction_contacts', ['economic_transaction_id' => $transaction->id, 'contact_id' => $contacts[0]->id]);
        $this->assertDatabaseMissing('economic_transaction_contacts', ['economic_transaction_id' => $transaction->id, 'contact_id' => $contacts[1]->id]);
        $this->assertDatabaseHas('contact_contact_label', ['contact_id' => $contacts[0]->id, 'contact_label_id' => $label->id]);
        $this->assertDatabaseHas('contact_record_relations', ['related_contact_id' => $contacts[0]->id]);
        $this->assertDatabaseMissing('contact_record_relations', ['related_contact_id' => $contacts[1]->id]);
    }

    public function test_merge_rejects_contacts_from_another_owner_and_stale_proposals(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $other = User::factory()->create();
        $own = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Own']);
        $hidden = Contact::create(['user_id' => $other->id, 'type' => 'person', 'display_name' => 'Hidden']);
        $this->actingAs($user)->post(route('contacts.duplicates.store'), [
            'groups' => [['primary_id' => $own->id, 'duplicate_ids' => [$hidden->id]]],
        ])->assertNotFound();
        $second = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Different']);
        $this->postJson(route('contacts.duplicates.store'), [
            'groups' => [['primary_id' => $own->id, 'duplicate_ids' => [$second->id]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('groups');
        $this->assertSame(3, Contact::query()->count());
    }

    public function test_local_values_survive_remote_sync_and_selected_remote_fields_can_be_applied(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = $this->source($user, 'Nextcloud');
        $contact = $this->import($source, 'ada', 'Ada Remota', 'remote@example.com');
        $this->actingAs($user)->put(route('contacts.update', $contact), [
            'type' => 'person',
            'display_name' => 'Ada Local',
            'notes' => 'Nota local',
            'emails' => [['value' => 'local@example.com', 'type' => 'home', 'preferred' => true]],
        ])->assertRedirect(route('contacts.show', $contact));
        $local = $contact->records()->whereNull('contact_source_id')->firstOrFail();
        $remote = $contact->records()->whereNotNull('contact_source_id')->firstOrFail();

        $this->import($source, 'ada', 'Ada Remota Nueva', 'new@example.com');
        $this->assertSame('Ada Local', $contact->fresh()->display_name);
        $this->assertSame('Nota local', $local->fresh()->notes);
        $this->get(route('contacts.index'))->assertInertia(fn ($page) => $page
            ->where('contacts.0.email', 'local@example.com')
        );

        $this->put(route('contacts.remote-fields.update', $contact), [
            'record_id' => $remote->id,
            'fields' => ['emails'],
        ])->assertRedirect(route('contacts.show', $contact));
        $this->assertDatabaseHas('contact_emails', ['contact_record_id' => $local->id, 'value' => 'new@example.com']);
        $this->assertSame('Ada Local', $contact->fresh()->display_name);
        $this->assertSame('Nota local', $local->fresh()->notes);
        $this->get(route('contacts.show', $contact))->assertInertia(fn ($page) => $page
            ->where('contact.records.0.source_id', null)
            ->has('sources', 1)
        );

        $this->putJson(route('contacts.remote-fields.update', $contact), [
            'record_id' => $remote->id,
            'fields' => ['unsupported'],
        ])->assertUnprocessable()->assertJsonValidationErrors('fields.0');

        $this->put(route('contacts.update', $contact), [
            'type' => 'person',
            'display_name' => 'Ada Local',
            'notes' => 'Nota local',
            'emails' => [],
        ])->assertRedirect();
        $this->get(route('contacts.index'))->assertInertia(fn ($page) => $page
            ->where('contacts.0.email', null)
        );
    }

    public function test_merged_sources_keep_their_categories_when_one_source_removes_its_own(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $firstSource = $this->source($user, 'Primera');
        $secondSource = $this->source($user, 'Segunda');
        $parser = app(VCardParser::class);
        $import = app(ImportExternalContact::class);
        $first = $import->execute($firstSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nEMAIL:ada@example.com\r\nCATEGORIES:Familia\r\nEND:VCARD\r\n", 'first'))->contact;
        $second = $import->execute($secondSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nEMAIL:ada@example.com\r\nCATEGORIES:Trabajo\r\nEND:VCARD\r\n", 'second'))->contact;

        $this->actingAs($user)->post(route('contacts.duplicates.store'), [
            'groups' => [['primary_id' => $first->id, 'duplicate_ids' => [$second->id]]],
        ])->assertRedirect();
        $this->assertSame(['Familia', 'Trabajo'], $first->labels()->orderBy('name')->pluck('name')->all());

        $import->execute($firstSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nEMAIL:ada@example.com\r\nEND:VCARD\r\n", 'first'));
        $this->assertSame(['Trabajo'], $first->labels()->pluck('name')->all());
    }

    public function test_remote_photo_sync_does_not_replace_the_preferred_local_photo(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = $this->source($user, 'Nextcloud');
        $parser = app(VCardParser::class);
        $import = app(ImportExternalContact::class);
        $remotePhoto = UploadedFile::fake()->image('remote.png', 40, 40)->get();
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nPHOTO;ENCODING=b:".base64_encode($remotePhoto)."\r\nEND:VCARD\r\n";
        $contact = $import->execute($source, $parser->parse($vcard, 'ada'))->contact;

        $this->actingAs($user)->post(route('contacts.update', $contact), [
            '_method' => 'put',
            'type' => 'person',
            'display_name' => 'Ada Local',
            'photo' => UploadedFile::fake()->image('local.png', 40, 40),
        ])->assertRedirect();
        $localId = $contact->records()->whereNull('contact_source_id')->firstOrFail()->id;
        $this->assertSame($localId, $contact->fresh()->preferred_photo_record_id);

        $import->execute($source, $parser->parse($vcard, 'ada'));
        $this->assertSame($localId, $contact->fresh()->preferred_photo_record_id);
    }

    private function source(User $user, string $name): ContactSource
    {
        return ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => $name,
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
    }

    private function import(ContactSource $source, string $remoteId, string $name, string $email): Contact
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:".$name."\r\nEMAIL:".$email."\r\nEND:VCARD\r\n";
        $record = app(ImportExternalContact::class)->execute(
            $source,
            app(VCardParser::class)->parse($vcard, $remoteId),
        );

        return $record->contact;
    }
}
