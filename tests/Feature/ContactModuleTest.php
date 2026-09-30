<?php

namespace Tests\Feature;

use App\Actions\Contacts\ImportExternalContact;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\SyncContactSourceJob;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\ContactSource;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Contacts\Providers\VCardParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use MStilkerich\CardDavClient\AddressbookCollection;
use MStilkerich\CardDavClient\Services\Discovery;
use Tests\TestCase;

class ContactModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_personal_contact_with_multivalue_data(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/contacts', [
            'type' => 'person', 'display_name' => 'Ada Lovelace',
            'given_name' => 'Ada', 'family_name' => 'Lovelace',
            'emails' => [['value' => 'ada@example.com', 'type' => 'work', 'preferred' => true]],
        ])->assertSuccessful()->assertJsonPath('data.display_name', 'Ada Lovelace');

        $this->assertDatabaseHas('contacts', ['user_id' => $user->id, 'display_name' => 'Ada Lovelace']);
        $this->assertDatabaseHas('contact_emails', ['value' => 'ada@example.com', 'preferred' => 1]);
    }

    public function test_another_user_cannot_read_or_update_private_contact_by_uuid(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $attacker->assignRole('user');
        $contact = Contact::create(['user_id' => $owner->id, 'type' => 'person', 'display_name' => 'Private']);
        Sanctum::actingAs($attacker);

        $this->getJson('/api/v1/contacts/'.$contact->id)->assertForbidden();
        $this->putJson('/api/v1/contacts/'.$contact->id, [
            'type' => 'person', 'display_name' => 'Stolen',
        ])->assertForbidden();
        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'display_name' => 'Private']);
    }

    public function test_nonmember_cannot_create_household_contact(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $household = Household::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/contacts', [
            'household_id' => $household->id,
            'type' => 'organization', 'display_name' => 'Hidden Organization',
        ])->assertForbidden();
        $this->assertDatabaseMissing('contacts', ['display_name' => 'Hidden Organization']);
    }

    public function test_member_can_view_household_contact_but_not_another_private_contact(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('user');
        $household = Household::factory()->create();
        HouseholdMember::factory()->create(['user_id' => $member->id, 'household_id' => $household->id]);
        $shared = Contact::create(['household_id' => $household->id, 'type' => 'organization', 'display_name' => 'Shared']);
        $private = Contact::create(['user_id' => $owner->id, 'type' => 'person', 'display_name' => 'Private']);
        Sanctum::actingAs($member);

        $this->getJson('/api/v1/contacts')->assertSuccessful()->assertJsonFragment(['display_name' => 'Shared'])->assertDontSee($private->id);
        $this->getJson('/api/v1/contacts/'.$shared->id)->assertSuccessful();
    }

    public function test_contacts_page_search_finds_only_visible_matching_contacts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $other = User::factory()->create();
        $household = Household::factory()->create();
        HouseholdMember::factory()->create(['user_id' => $user->id, 'household_id' => $household->id]);
        Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada Local']);
        Contact::create(['household_id' => $household->id, 'type' => 'person', 'display_name' => 'Ada Shared']);
        Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Grace']);
        Contact::create(['user_id' => $other->id, 'type' => 'person', 'display_name' => 'Ada Hidden']);

        $this->actingAs($user)->get(route('contacts.index', ['search' => ' Ada ']))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('contacts/Index')
            ->where('filters.search', 'Ada')
            ->has('contacts', 2)
            ->where('contacts.0.display_name', 'Ada Local')
            ->where('contacts.1.display_name', 'Ada Shared')
            );
    }

    public function test_contacts_search_partial_reload_returns_only_requested_props(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);
        Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Grace']);

        $this->actingAs($user)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'contacts/Index',
            'X-Inertia-Partial-Data' => 'contacts,filters',
        ])->get(route('contacts.index', ['search' => 'Ada']))
            ->assertOk()
            ->assertJsonPath('props.filters.search', 'Ada')
            ->assertJsonCount(1, 'props.contacts')
            ->assertJsonPath('props.contacts.0.display_name', 'Ada')
            ->assertJsonMissingPath('props.households')
            ->assertJsonMissingPath('props.labels');
    }

    public function test_contacts_page_loads_every_visible_contact_with_email_and_phone(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $first = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'A Contact']);
        $record = $first->records()->create(['formatted_name' => 'A Contact']);
        DB::table('contact_emails')->insert(['contact_record_id' => $record->id, 'value' => 'ada@example.com', 'type' => 'work', 'preferred' => true]);
        DB::table('contact_phones')->insert(['contact_record_id' => $record->id, 'value' => '+34 600 123 456', 'type' => 'mobile', 'preferred' => true]);
        for ($index = 1; $index <= 35; $index++) {
            Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Contact '.$index]);
        }

        $this->actingAs($user)->get(route('contacts.index'))
            ->assertInertia(fn ($page) => $page
                ->component('contacts/Index')
                ->has('contacts', 36)
                ->where('contacts.0.email', 'ada@example.com')
                ->where('contacts.0.phone', '+34 600 123 456')
            );
    }

    public function test_contacts_search_matches_email_and_phone(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Grace']);
        $record = $contact->records()->create(['formatted_name' => 'Grace']);
        DB::table('contact_emails')->insert(['contact_record_id' => $record->id, 'value' => 'grace@example.com', 'preferred' => false]);
        DB::table('contact_phones')->insert(['contact_record_id' => $record->id, 'value' => '+34 600 123 456', 'preferred' => false]);

        $this->actingAs($user)->get(route('contacts.index', ['search' => 'grace@example.com']))
            ->assertInertia(fn ($page) => $page->has('contacts', 1)->where('contacts.0.display_name', 'Grace'));
        $this->actingAs($user)->get(route('contacts.index', ['search' => '600 123']))
            ->assertInertia(fn ($page) => $page->has('contacts', 1)->where('contacts.0.display_name', 'Grace'));
    }

    public function test_contact_labels_can_be_created_assigned_and_used_to_filter_the_web_list(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);
        Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Grace']);

        $this->actingAs($user)->post(route('contacts.labels.store'), ['name' => 'Familia'])->assertRedirect();
        $label = ContactLabel::query()->where('user_id', $user->id)->firstOrFail();
        $this->put(route('contacts.labels.update', $contact), ['label_ids' => [$label->id]])->assertRedirect();
        $this->get(route('contacts.show', $contact))
            ->assertInertia(fn ($page) => $page
                ->component('contacts/Show')
                ->where('contact.labels.0.name', 'Familia')
            );
        $this->get(route('contacts.edit', $contact))
            ->assertInertia(fn ($page) => $page
                ->component('contacts/Edit')
                ->where('labels.0.id', $label->id)
            );
        $this->get(route('contacts.index', ['label' => $label->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.label', $label->id)
                ->has('contacts', 1)
                ->where('contacts.0.display_name', 'Ada')
                ->where('contacts.0.labels.0.name', 'Familia')
            );
    }

    public function test_favorite_contacts_combine_default_custom_and_manual_labels_without_duplicates(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $defaultSource = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Default',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $customSource = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Custom',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav', 'favorite_label' => 'VIP'],
        ]);
        $parser = app(VCardParser::class);
        $import = app(ImportExternalContact::class);
        $import->execute($defaultSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nCATEGORIES:Starred\r\nEND:VCARD\r\n", 'ada'));
        $import->execute($customSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Grace\r\nCATEGORIES:VIP,Starred\r\nEND:VCARD\r\n", 'grace'));
        $import->execute($customSource, $parser->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Linus\r\nCATEGORIES:Starred\r\nEND:VCARD\r\n", 'linus'));
        $local = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Mary']);
        $starred = ContactLabel::query()->where('user_id', $user->id)->where('name', 'Starred')->firstOrFail();
        $local->labels()->attach($starred->id);

        $this->actingAs($user)->get(route('contacts.index'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('contacts', 4)
            ->where('contacts.0.display_name', 'Ada')
            ->where('contacts.0.is_favorite', true)
            ->where('contacts.1.display_name', 'Grace')
            ->where('contacts.1.is_favorite', true)
            ->where('contacts.2.display_name', 'Linus')
            ->where('contacts.2.is_favorite', false)
            ->where('contacts.3.display_name', 'Mary')
            ->where('contacts.3.is_favorite', true)
            );
        $vip = ContactLabel::query()->where('user_id', $user->id)->where('name', 'VIP')->firstOrFail();
        $this->get(route('contacts.index', ['label' => $vip->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.display_name', 'Grace')
            ->where('contacts.0.is_favorite', true)
            );
    }

    public function test_contact_overview_shows_its_sources_and_editing_requires_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $other = User::factory()->create();
        $other->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $owner->id,
            'provider' => 'carddav',
            'name' => 'Nextcloud',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $contact = Contact::create(['user_id' => $owner->id, 'type' => 'person', 'display_name' => 'Ada']);
        $contact->records()->create(['contact_source_id' => $source->id, 'formatted_name' => 'Ada']);

        $this->actingAs($owner)->get(route('contacts.show', $contact))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('contacts/Show')
            ->where('contact.display_name', 'Ada')
            ->where('contact.records.0.source_id', $source->id)
            ->where('sources.0.name', 'Nextcloud')
            ->where('can.update', true)
            ->where('can.delete', true)
            );
        $this->get(route('contacts.edit', $contact))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('contacts/Edit')
            ->where('sources.0.name', 'Nextcloud')
            );
        $this->actingAs($other)->get(route('contacts.edit', $contact))->assertForbidden();
    }

    public function test_contact_cannot_be_assigned_another_users_label(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $other = User::factory()->create();
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);
        $label = ContactLabel::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->putJson(route('contacts.labels.update', $contact), ['label_ids' => [$label->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('label_ids.0');
        $this->assertDatabaseCount('contact_contact_label', 0);
    }

    public function test_contact_labels_can_be_cleared_and_another_users_label_is_not_a_filter(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $other = User::factory()->create();
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);
        $label = ContactLabel::factory()->create(['user_id' => $user->id]);
        $otherLabel = ContactLabel::factory()->create(['user_id' => $other->id]);
        $contact->labels()->attach($label->id);

        $this->actingAs($user)->put(route('contacts.labels.update', $contact), ['label_ids' => []])->assertRedirect();
        $this->assertDatabaseCount('contact_contact_label', 0);
        $this->get(route('contacts.index', ['label' => $otherLabel->id]))
            ->assertInertia(fn ($page) => $page
                ->where('filters.label', null)
                ->has('contacts', 1)
                ->has('labels', 1)
            );
    }

    public function test_owner_can_delete_a_label_without_deleting_its_contact(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);
        $label = ContactLabel::factory()->create(['user_id' => $user->id]);
        $contact->labels()->attach($label->id);

        $this->actingAs($user)->delete(route('contacts.labels.destroy', $label))
            ->assertRedirect(route('contacts.index'));
        $this->assertModelMissing($label);
        $this->assertModelExists($contact);
        $this->assertDatabaseCount('contact_contact_label', 0);
    }

    public function test_contact_label_cannot_be_deleted_by_another_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $label = ContactLabel::factory()->create();

        $this->actingAs($user)->delete(route('contacts.labels.destroy', $label))->assertNotFound();
        $this->assertModelExists($label);
    }

    public function test_contact_source_resource_never_exposes_carddav_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);

        $this->mockAddressbooks();
        Queue::fake([SyncContactSourceJob::class]);
        $this->postJson('/api/v1/contact-sources', [
            'name' => 'Private CardDAV',
            'server_url' => 'https://93.184.216.34/dav',
            'username' => 'ada',
            'app_password' => 'very-secret-password',
            'selected_collections' => ['https://93.184.216.34/dav/people'],
        ])->assertSuccessful()->assertDontSee('very-secret-password')->assertDontSee('ada');

        $this->assertDatabaseCount('contact_sources', 1);
        Queue::assertPushed(SyncContactSourceJob::class, 1);
    }

    public function test_sources_page_serializes_collections_as_an_array(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $source->collections()->create([
            'remote_id' => 'https://93.184.216.34/dav/people',
            'remote_href' => 'https://93.184.216.34/dav/people',
            'name' => 'People',
            'enabled' => true,
        ]);

        $this->actingAs($user)->get(route('contacts.sources.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('contacts/Sources')
                ->has('sources.0.collections', 1)
                ->where('sources.0.collections.0.name', 'People')
                ->where('sources.0.collections.0.enabled', true)
            );
    }

    public function test_owner_can_discover_carddav_on_an_explicit_server_without_saving_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->once())->method('discoverAddressbooks')->willReturn([]);
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->postJson(route('contacts.sources.discover-server'), [
                'domain' => '93.184.216.34',
                'username' => 'ada',
                'app_password' => 'secret',
            ])
            ->assertOk()
            ->assertJsonPath('server_url', 'https://93.184.216.34')
            ->assertDontSee('secret');

        $this->assertDatabaseCount('contact_sources', 0);
    }

    public function test_user_without_source_permission_cannot_start_carddav_discovery(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->never())->method('discoverAddressbooks');
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->postJson(route('contacts.sources.discover-server'), [
                'domain' => '93.184.216.34',
                'username' => 'ada',
                'app_password' => 'secret',
            ])
            ->assertForbidden();
    }

    public function test_discovery_rejects_private_server_addresses(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->never())->method('discoverAddressbooks');
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->postJson(route('contacts.sources.discover-server'), [
                'domain' => '127.0.0.1',
                'username' => 'ada',
                'app_password' => 'secret',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('contact_sources', 0);
    }

    public function test_owner_can_edit_carddav_source_without_reentering_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Original',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => false,
            'sync_enabled' => false,
        ]);

        $this->mockAddressbooks();
        $this->actingAs($user)
            ->put(route('contacts.sources.update', $source), [
                'name' => 'Actualizada',
                'server_url' => 'https://93.184.216.34/new-dav',
                'selected_collections' => ['https://93.184.216.34/dav/people'],
            ])
            ->assertRedirect(route('contacts.sources.index'));

        $source->refresh();
        $this->assertSame('Actualizada', $source->name);
        $this->assertSame(['server_url' => 'https://93.184.216.34/new-dav', 'favorite_label' => 'Starred'], $source->provider_configuration);
        $this->assertSame(['username' => 'ada', 'password' => 'secret'], $source->encrypted_credentials);
        $this->assertFalse($source->enabled);
        $this->assertFalse($source->sync_enabled);
    }

    public function test_favorite_label_can_be_changed_without_remote_discovery_and_omitted_on_later_edits(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        $source->collections()->create([
            'remote_id' => 'https://93.184.216.34/dav/people',
            'remote_href' => 'https://93.184.216.34/dav/people',
            'name' => 'People',
            'enabled' => true,
        ]);
        Queue::fake([SyncContactSourceJob::class]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->never())->method('discoverAddressbooks');
        $this->app->instance(Discovery::class, $discovery);
        $request = [
            'name' => 'CardDAV',
            'server_url' => 'https://93.184.216.34/dav',
            'selected_collections' => ['https://93.184.216.34/dav/people'],
        ];

        $this->actingAs($user)->put(route('contacts.sources.update', $source), $request + ['favorite_label' => 'VIP'])
            ->assertRedirect(route('contacts.sources.index'));
        $this->assertSame('VIP', $source->fresh()->favoriteLabel());
        Queue::assertNotPushed(SyncContactSourceJob::class);

        $this->put(route('contacts.sources.update', $source), $request)
            ->assertRedirect(route('contacts.sources.index'));
        $this->assertSame('VIP', $source->fresh()->favoriteLabel());

        $this->put(route('contacts.sources.update', $source), $request + ['favorite_label' => str_repeat('x', 81)])
            ->assertSessionHasErrors('favorite_label');
        $this->assertSame('VIP', $source->fresh()->favoriteLabel());
    }

    public function test_api_can_replace_carddav_credentials_without_exposing_them(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Original',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        Sanctum::actingAs($user);
        $this->mockAddressbooks();

        $this->putJson('/api/v1/contact-sources/'.$source->id, [
            'name' => 'Updated',
            'server_url' => 'https://93.184.216.34/dav',
            'username' => 'grace',
            'app_password' => 'new-secret',
            'selected_collections' => ['https://93.184.216.34/dav/people'],
            'enabled' => false,
            'sync_enabled' => false,
        ])->assertSuccessful()->assertDontSee('grace')->assertDontSee('new-secret');

        $source->refresh();
        $this->assertSame(['username' => 'grace', 'password' => 'new-secret'], $source->encrypted_credentials);
        $this->assertFalse($source->enabled);
        $this->assertFalse($source->sync_enabled);
    }

    public function test_failed_carddav_connection_test_returns_an_error_toast(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Private CardDAV',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        Http::fake(['*' => Http::response('', 500)]);

        $this->actingAs($user)
            ->from('/contacts/sources')
            ->post('/contacts/sources/'.$source->id.'/test')
            ->assertRedirect('/contacts/sources')
            ->assertInertiaFlash('toast', [
                'type' => 'error',
                'message' => 'No se pudo comprobar la conexión CardDAV. Revisa la URL, las credenciales y la conectividad.',
            ]);
    }

    public function test_successful_carddav_connection_test_returns_a_success_toast(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'Private CardDAV',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->once())->method('discoverAddressbooks')->willReturn([]);
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->from('/contacts/sources')
            ->post('/contacts/sources/'.$source->id.'/test')
            ->assertRedirect('/contacts/sources')
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Conexión CardDAV correcta.',
            ]);
    }

    public function test_discovering_no_carddav_books_reports_an_empty_result(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->method('discoverAddressbooks')->willReturn([]);
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->from(route('contacts.sources.index'))
            ->post(route('contacts.sources.discover', $source))
            ->assertRedirect(route('contacts.sources.index'))
            ->assertInertiaFlash('toast', [
                'type' => 'info',
                'message' => 'La conexión funciona, pero no se encontraron libretas CardDAV.',
            ]);

        $this->assertDatabaseCount('contact_collections', 0);
    }

    public function test_failed_carddav_discovery_reports_an_error_instead_of_a_server_error(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'authentication_type' => 'app_password',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->method('discoverAddressbooks')->willThrowException(new \RuntimeException('Remote connection failed.'));
        $this->app->instance(Discovery::class, $discovery);

        $this->actingAs($user)
            ->from(route('contacts.sources.index'))
            ->post(route('contacts.sources.discover', $source))
            ->assertRedirect(route('contacts.sources.index'))
            ->assertInertiaFlash('toast', [
                'type' => 'error',
                'message' => 'No se pudieron descubrir las libretas CardDAV. Comprueba la URL y las credenciales.',
            ]);

        $this->assertDatabaseCount('contact_collections', 0);
    }

    public function test_vcard_parser_normalizes_name_email_and_photo(): void
    {
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:abc\r\nFN:Ada Lovelace\r\nN:Lovelace;Ada;;;\r\nEMAIL;TYPE=WORK:ada@example.com\r\nPHOTO;ENCODING=b:".base64_encode('photo bytes')."\r\nEND:VCARD\r\n";
        $contact = app(VCardParser::class)->parse($vcard, 'remote-abc');

        $this->assertSame('Ada Lovelace', $contact->formattedName);
        $this->assertSame('Ada', $contact->givenName);
        $this->assertSame('ada@example.com', $contact->emails[0]->value);
        $this->assertSame('photo bytes', $contact->photoBytes);
    }

    public function test_vcard_import_keeps_anniversary_partial_birthday_relation_and_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $photo = UploadedFile::fake()->image('ada.png', 40, 40)->get();
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:ada\r\nFN:Ada Lovelace\r\nN:Lovelace;Ada;Byron;;\r\nNICKNAME:Enchantress\r\nBDAY:--12-10\r\nANNIVERSARY:20200512\r\nRELATED;TYPE=spouse;VALUE=text:Grace\r\nPHOTO;ENCODING=b:".base64_encode($photo)."\r\nEND:VCARD\r\n";
        $data = app(VCardParser::class)->parse($vcard, 'ada');
        $record = app(ImportExternalContact::class)->execute($source, $data);

        $this->assertSame('Byron', $record->additional_name);
        $this->assertSame('Enchantress', $record->nickname);
        $this->assertNull($record->birthday);
        $this->assertDatabaseHas('contact_record_dates', ['contact_record_id' => $record->id, 'kind' => 'birthday', 'value' => '--12-10']);
        $this->assertDatabaseHas('contact_record_dates', ['contact_record_id' => $record->id, 'kind' => 'anniversary', 'value' => '2020-05-12']);
        $this->assertDatabaseHas('contact_record_relations', ['contact_record_id' => $record->id, 'type' => 'spouse', 'name' => 'Grace']);
        $this->assertDatabaseHas('contact_record_photos', ['contact_record_id' => $record->id]);
    }

    public function test_carddav_categories_are_imported_visible_and_updated_without_removing_manual_labels(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $parser = app(VCardParser::class);
        $import = app(ImportExternalContact::class);
        $initial = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nCATEGORIES:Familia,Trabajo\\, remoto\r\nCATEGORIES:Familia\r\nEND:VCARD\r\n";
        $data = $parser->parse($initial, 'ada');
        $this->assertSame(['Familia', 'Trabajo, remoto'], $data->categories);

        $record = $import->execute($source, $data);
        $contact = $record->contact;
        $family = ContactLabel::query()->where('user_id', $user->id)->where('name', 'Familia')->firstOrFail();
        $remote = ContactLabel::query()->where('user_id', $user->id)->where('name', 'Trabajo, remoto')->firstOrFail();
        $this->assertDatabaseHas('contact_contact_label', ['contact_id' => $contact->id, 'contact_label_id' => $family->id, 'manual' => false, 'imported' => true]);
        $this->actingAs($user)->delete(route('contacts.labels.destroy', $family))->assertForbidden();

        $this->actingAs($user)->get(route('contacts.index', ['label' => $family->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('contacts', 1)
            ->where('contacts.0.labels.0.name', 'Familia')
            ->where('labels.0.imported', true)
            );
        $this->put(route('contacts.labels.update', $contact), ['label_ids' => [$family->id]])->assertRedirect();
        $this->assertDatabaseHas('contact_contact_label', ['contact_id' => $contact->id, 'contact_label_id' => $family->id, 'manual' => true, 'imported' => true]);

        $updated = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nEND:VCARD\r\n";
        $import->execute($source, $parser->parse($updated, 'ada'));
        $this->assertDatabaseHas('contact_contact_label', ['contact_id' => $contact->id, 'contact_label_id' => $family->id, 'manual' => true, 'imported' => false]);
        $this->assertDatabaseMissing('contact_contact_label', ['contact_id' => $contact->id, 'contact_label_id' => $remote->id]);
    }

    public function test_household_carddav_categories_are_visible_to_household_members(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $member->assignRole('user');
        $household = Household::factory()->create();
        HouseholdMember::factory()->create(['user_id' => $member->id, 'household_id' => $household->id]);
        $source = ContactSource::create([
            'household_id' => $household->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        app(ImportExternalContact::class)->execute($source, app(VCardParser::class)->parse("BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nCATEGORIES:Familia\r\nEND:VCARD\r\n", 'ada'));
        $label = ContactLabel::query()->where('household_id', $household->id)->firstOrFail();

        $this->actingAs($member)->get(route('contacts.index', ['label' => $label->id]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->has('contacts', 1)
            ->where('labels.0.name', 'Familia')
            );
    }

    public function test_invalid_remote_photo_does_not_block_contact_import(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $vcard = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ada\r\nPHOTO;ENCODING=b:".base64_encode('invalid image bytes')."\r\nEND:VCARD\r\n";

        $record = app(ImportExternalContact::class)->execute(
            $source,
            app(VCardParser::class)->parse($vcard, 'ada'),
        );

        $this->assertSame('Ada', $record->formatted_name);
        $this->assertDatabaseMissing('contact_record_photos', ['contact_record_id' => $record->id]);
    }

    public function test_vcard_photo_uri_is_fetched_only_from_the_configured_origin(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $image = UploadedFile::fake()->image('ada.png', 40, 40)->get();
        Http::fake(['*' => Http::response($image, 200, ['Content-Type' => 'image/png'])]);

        $sameOrigin = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Ada\r\nPHOTO:https://93.184.216.34/photos/ada.png\r\nEND:VCARD\r\n";
        $data = app(VCardParser::class)->parse($sameOrigin, 'ada', href: 'https://93.184.216.34/dav/ada.vcf');
        $record = app(ImportExternalContact::class)->execute($source, $data);
        $this->assertDatabaseHas('contact_record_photos', ['contact_record_id' => $record->id]);

        $otherOrigin = "BEGIN:VCARD\r\nVERSION:4.0\r\nFN:Grace\r\nPHOTO:https://93.184.216.35/photos/grace.png\r\nEND:VCARD\r\n";
        $data = app(VCardParser::class)->parse($otherOrigin, 'grace', href: 'https://93.184.216.34/dav/grace.vcf');
        $record = app(ImportExternalContact::class)->execute($source, $data);
        $this->assertDatabaseMissing('contact_record_photos', ['contact_record_id' => $record->id]);
        Http::assertSentCount(1);
    }

    public function test_carddav_preview_lists_books_without_persisting_credentials(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->mockAddressbooks();

        $this->actingAs($user)->postJson(route('contacts.sources.preview'), [
            'provider' => 'carddav',
            'server_url' => 'https://93.184.216.34/dav',
            'username' => 'ada',
            'app_password' => 'secret',
        ])->assertOk()
            ->assertJsonPath('collections.0.name', 'people')
            ->assertJsonPath('collections.0.remote_id', 'https://93.184.216.34/dav/people')
            ->assertDontSee('secret');

        $this->assertDatabaseCount('contact_sources', 0);
    }

    public function test_preview_uses_stored_credentials_only_for_the_source_owner(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $other = User::factory()->create();
        $other->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $owner->id,
            'provider' => 'carddav',
            'name' => 'Private',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $this->mockAddressbooks();
        $payload = [
            'provider' => 'carddav',
            'source_id' => $source->id,
            'server_url' => 'https://93.184.216.34/dav',
        ];

        $this->actingAs($owner)->postJson(route('contacts.sources.preview'), $payload)
            ->assertOk()->assertJsonPath('collections.0.name', 'people')->assertDontSee('secret');
        $this->actingAs($other)->postJson(route('contacts.sources.preview'), $payload)
            ->assertForbidden()->assertDontSee('people');
    }

    public function test_carddav_source_requires_a_selected_book_from_the_current_server(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->mockAddressbooks();
        Sanctum::actingAs($user);
        $data = [
            'name' => 'CardDAV',
            'server_url' => 'https://93.184.216.34/dav',
            'username' => 'ada',
            'app_password' => 'secret',
        ];

        $this->postJson('/api/v1/contact-sources', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('selected_collections');
        $this->postJson('/api/v1/contact-sources', $data + [
            'selected_collections' => ['https://93.184.216.34/dav/other'],
        ])->assertUnprocessable()->assertJsonValidationErrors('selected_collections');
        $this->assertDatabaseCount('contact_sources', 0);
    }

    public function test_newly_discovered_books_remain_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $source->collections()->create([
            'remote_id' => 'https://93.184.216.34/dav/existing',
            'remote_href' => 'https://93.184.216.34/dav/existing',
            'name' => 'existing',
            'enabled' => true,
        ]);
        $this->mockAddressbooks();

        $this->actingAs($user)->post(route('contacts.sources.discover', $source))->assertRedirect();
        $this->assertDatabaseHas('contact_collections', [
            'contact_source_id' => $source->id,
            'name' => 'people',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('contact_collections', [
            'contact_source_id' => $source->id,
            'name' => 'existing',
            'enabled' => true,
        ]);
    }

    public function test_local_contact_stores_dates_relations_and_photo(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $related = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Grace']);

        $this->actingAs($user)->post(route('contacts.store'), [
            'type' => 'person',
            'display_name' => 'Ada',
            'nickname' => 'Enchantress',
            'additional_name' => 'Byron',
            'dates' => [['kind' => 'anniversary', 'value' => '2020-05-12']],
            'relations' => [['type' => 'friend', 'related_contact_id' => $related->id]],
            'photo' => UploadedFile::fake()->image('ada.png', 40, 40),
        ])->assertRedirect(route('contacts.index'));

        $contact = Contact::query()->where('display_name', 'Ada')->firstOrFail();
        $record = $contact->records()->firstOrFail();
        $this->assertSame('Enchantress', $record->nickname);
        $this->assertDatabaseHas('contact_record_dates', ['contact_record_id' => $record->id, 'kind' => 'anniversary', 'value' => '2020-05-12']);
        $this->assertDatabaseHas('contact_record_relations', ['contact_record_id' => $record->id, 'related_contact_id' => $related->id]);
        $this->assertDatabaseHas('contact_record_photos', ['contact_record_id' => $record->id]);
        $this->actingAs($user)->get(route('contacts.avatar', $contact))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_contact_photo_can_be_replaced_and_removed_from_the_edit_form(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $contact = Contact::create(['user_id' => $user->id, 'type' => 'person', 'display_name' => 'Ada']);

        $this->actingAs($user)->post(route('contacts.update', $contact), [
            '_method' => 'put',
            'type' => 'person',
            'display_name' => 'Ada',
            'photo' => UploadedFile::fake()->image('ada.png', 40, 40),
        ])->assertRedirect(route('contacts.show', $contact));

        $this->assertNotNull($contact->fresh()->preferred_photo_record_id);
        $this->actingAs($user)->post(route('contacts.update', $contact), [
            '_method' => 'put',
            'type' => 'person',
            'display_name' => 'Ada',
            'remove_photo' => true,
        ])->assertRedirect(route('contacts.show', $contact));

        $this->assertNull($contact->fresh()->preferred_photo_record_id);
        $this->assertDatabaseCount('contact_record_photos', 0);
    }

    public function test_last_selected_book_cannot_be_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'carddav',
            'name' => 'CardDAV',
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav'],
        ]);
        $book = $source->collections()->create([
            'remote_id' => 'https://93.184.216.34/dav/people',
            'remote_href' => 'https://93.184.216.34/dav/people',
            'name' => 'people',
            'enabled' => true,
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/contact-collections/'.$book->id, ['enabled' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $this->assertTrue($book->fresh()->enabled);
    }

    public function test_local_contact_cannot_link_to_another_users_private_contact(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('user');
        $other = User::factory()->create();
        $hidden = Contact::create(['user_id' => $other->id, 'type' => 'person', 'display_name' => 'Hidden']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/contacts', [
            'type' => 'person',
            'display_name' => 'Ada',
            'relations' => [['type' => 'friend', 'related_contact_id' => $hidden->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('relations.0.related_contact_id');

        $this->assertDatabaseMissing('contacts', ['display_name' => 'Ada']);
    }

    private function mockAddressbooks(): void
    {
        $book = $this->createMock(AddressbookCollection::class);
        $book->method('getName')->willReturn('people');
        $book->method('getUri')->willReturn('/dav/people');
        $discovery = $this->createMock(Discovery::class);
        $discovery->method('discoverAddressbooks')->willReturn([$book]);
        $this->app->instance(Discovery::class, $discovery);
    }
}
