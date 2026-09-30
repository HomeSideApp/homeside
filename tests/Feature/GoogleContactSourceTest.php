<?php

namespace Tests\Feature;

use App\Actions\Contacts\SyncContactCollection;
use App\Jobs\SyncContactSourceJob;
use App\Models\Contact;
use App\Models\ContactRecord;
use App\Models\ContactSource;
use App\Models\User;
use App\Services\Contacts\Providers\GooglePeopleContactProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleContactSourceTest extends TestCase
{
    use RefreshDatabase;

    private function source(): ContactSource
    {
        $user = User::factory()->create();

        $source = ContactSource::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'name' => 'Google',
            'authentication_type' => 'oauth2',
            'encrypted_tokens' => ['access_token' => 'access-token', 'refresh_token' => 'refresh-token'],
            'token_expires_at' => now()->addHour(),
            'provider_configuration' => ['google_sub' => 'google-1', 'google_email' => 'owner@example.com', 'favorite_label' => 'Starred'],
            'enabled' => true,
            'sync_enabled' => true,
        ]);
        $source->collections()->create([
            'remote_id' => 'all',
            'remote_href' => 'people/me/connections',
            'name' => 'Todos los contactos',
            'enabled' => true,
            'read_only' => true,
        ]);

        return $source;
    }

    public function test_google_source_needs_collection_selection_and_stores_tokens_encrypted(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->admin()->create();
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake([
            'people.googleapis.com/v1/people/me/connections*' => Http::response(['connections' => []]),
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'another-google-account',
            'email' => 'contacts@example.com',
            'email_verified' => true,
            'token' => 'secret-access-token',
            'refreshToken' => 'secret-refresh-token',
        ]));

        $this->actingAs($owner)->get(route('contacts.sources.google.callback'))
            ->assertRedirect(route('contacts.sources.index', ['google_draft' => 1]));
        $this->post(route('contacts.sources.google.store'), [
            'name' => 'Mis contactos',
            'favorite_label' => 'Starred',
            'selected_collections' => [],
        ])->assertSessionHasErrors('selected_collections');
        $this->assertDatabaseMissing('contact_sources', ['provider' => 'google']);

        $this->post(route('contacts.sources.google.store'), [
            'name' => 'Mis contactos',
            'favorite_label' => 'Starred',
            'selected_collections' => ['all'],
        ])->assertRedirect(route('contacts.sources.index'));
        $source = ContactSource::query()->where('provider', 'google')->firstOrFail();
        $this->assertSame($owner->id, $source->user_id);
        $this->assertNull($source->household_id);
        $this->assertSame('secret-refresh-token', $source->encrypted_tokens['refresh_token']);
        $this->assertStringNotContainsString('secret-refresh-token', (string) DB::table('contact_sources')->where('id', $source->id)->value('encrypted_tokens'));
        $this->assertSame('all', $source->collections()->firstOrFail()->remote_id);
        Queue::assertPushed(SyncContactSourceJob::class);
    }

    public function test_people_contact_maps_labels_photo_and_dates(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'people.googleapis.com/v1/contactGroups*' => Http::response(['contactGroups' => [
                ['resourceName' => 'contactGroups/friends', 'name' => 'Amigos'],
            ]]),
            'people.googleapis.com/v1/people/me/connections*' => Http::response([
                'connections' => [[
                    'resourceName' => 'people/123',
                    'names' => [['displayName' => 'Ada Lovelace', 'givenName' => 'Ada']],
                    'emailAddresses' => [['value' => 'ada@example.com', 'type' => 'home', 'metadata' => ['primary' => true]]],
                    'birthdays' => [['date' => ['month' => 12, 'day' => 10]]],
                    'memberships' => [
                        ['contactGroupMembership' => ['contactGroupResourceName' => 'contactGroups/friends']],
                        ['contactGroupMembership' => ['contactGroupResourceName' => 'contactGroups/starred']],
                    ],
                    'photos' => [['url' => 'https://lh3.googleusercontent.com/photo.jpg', 'default' => false]],
                ]],
                'nextSyncToken' => 'sync-1',
            ]),
            'lh3.googleusercontent.com/*' => Http::response('image-data', 200),
        ]);
        $source = $this->source();

        $result = app(GooglePeopleContactProvider::class)->synchronize($source->collections->first(), null);
        $this->assertCount(1, $result->contacts);
        $this->assertSame('Ada Lovelace', $result->contacts[0]->formattedName);
        $this->assertSame(['Amigos', 'Starred'], $result->contacts[0]->categories);
        $this->assertSame('image-data', $result->contacts[0]->photoBytes);
        $this->assertSame('12-10', $result->contacts[0]->dates[0]['value']);
        $this->assertTrue($result->fullRunComplete);
    }

    public function test_full_sync_finishes_after_all_pages_before_marking_missing_contacts_deleted(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'people.googleapis.com/v1/contactGroups*' => Http::response(['contactGroups' => []]),
            'people.googleapis.com/v1/people/me/connections*' => Http::sequence()
                ->push(['connections' => [['resourceName' => 'people/first', 'names' => [['displayName' => 'First']]]], 'nextPageToken' => 'page-2'])
                ->push(['connections' => [['resourceName' => 'people/second', 'names' => [['displayName' => 'Second']]]], 'nextSyncToken' => 'sync-2']),
        ]);
        $source = $this->source();
        $collection = $source->collections->first();
        $staleContact = Contact::create(['user_id' => $source->user_id, 'type' => 'person', 'display_name' => 'Removed']);
        $staleRecord = $staleContact->records()->create([
            'contact_source_id' => $source->id,
            'remote_id' => 'people/removed',
            'formatted_name' => 'Removed',
        ]);
        $staleRecord->collections()->attach($collection->id);

        $sync = app(SyncContactCollection::class);
        $this->assertTrue($sync->execute($collection));
        $this->assertNull($staleRecord->fresh()->remote_deleted_at);
        $this->assertFalse($sync->execute($collection->fresh(['source', 'syncState'])));
        $this->assertNotNull($staleRecord->fresh()->remote_deleted_at);
        $this->assertSame(2, ContactRecord::query()->where('contact_source_id', $source->id)->whereNull('remote_deleted_at')->count());
        $this->assertSame('sync-2', $collection->syncState()->first()->cursor);
    }

    public function test_expired_incremental_cursor_restarts_a_full_sync(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'people.googleapis.com/v1/contactGroups*' => Http::response(['contactGroups' => []]),
            'people.googleapis.com/v1/people/me/connections*' => Http::sequence()
                ->push(['error' => ['status' => 'EXPIRED_SYNC_TOKEN']], 400)
                ->push(['connections' => [['resourceName' => 'people/current', 'names' => [['displayName' => 'Current']]]], 'nextSyncToken' => 'fresh-token']),
        ]);
        $source = $this->source();
        $collection = $source->collections()->firstOrFail();
        $collection->syncState()->create([
            'contact_source_id' => $source->id,
            'cursor' => 'expired-token',
            'cursor_type' => 'google-people',
            'provider_state' => [],
        ]);

        $this->assertFalse(app(SyncContactCollection::class)->execute($collection));
        $this->assertSame('fresh-token', $collection->syncState()->firstOrFail()->cursor);
        $this->assertSame(1, ContactRecord::query()->where('contact_source_id', $source->id)->count());
        Http::assertSentCount(3);
    }

    public function test_transient_people_error_does_not_delete_contacts_or_replace_cursor(): void
    {
        Http::preventStrayRequests();
        Http::fake(['people.googleapis.com/v1/people/me/connections*' => Http::response(['error' => ['message' => 'Rate limited']], 429)]);
        $source = $this->source();
        $collection = $source->collections()->firstOrFail();
        $state = $collection->syncState()->create([
            'contact_source_id' => $source->id,
            'cursor' => 'current-token',
            'cursor_type' => 'google-people',
            'provider_state' => [],
        ]);
        $contact = Contact::create(['user_id' => $source->user_id, 'type' => 'person', 'display_name' => 'Saved']);
        $record = $contact->records()->create([
            'contact_source_id' => $source->id,
            'remote_id' => 'people/saved',
            'formatted_name' => 'Saved',
        ]);
        $record->collections()->attach($collection->id);

        try {
            app(SyncContactCollection::class)->execute($collection);
            $this->fail('The People API failure was ignored.');
        } catch (RequestException) {
            $this->assertNull($record->fresh()->remote_deleted_at);
            $this->assertSame('current-token', $state->fresh()->cursor);
        }
    }

    public function test_revoked_google_permission_requires_reconnection_without_deleting_contacts(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'people.googleapis.com/v1/people/me/connections*' => Http::response(['error' => 'Unauthorized'], 401),
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        $source = $this->source();
        $collection = $source->collections()->firstOrFail();
        $contact = Contact::create(['user_id' => $source->user_id, 'type' => 'person', 'display_name' => 'Saved']);
        $record = $contact->records()->create([
            'contact_source_id' => $source->id,
            'remote_id' => 'people/saved',
            'formatted_name' => 'Saved',
        ]);
        $record->collections()->attach($collection->id);

        try {
            app(SyncContactCollection::class)->execute($collection);
            $this->fail('The revoked Google permission was ignored.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Google contact source requires reconnection.', $exception->getMessage());
            $this->assertFalse($source->fresh()->sync_enabled);
            $this->assertSame('reconnect_required', $source->fresh()->last_sync_status);
            $this->assertNull($record->fresh()->remote_deleted_at);
        }
    }
}
