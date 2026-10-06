<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contacts\DiscoverContactCollections;
use App\Actions\Contacts\SaveContactSource;
use App\Actions\Contacts\SetContactCollectionEnabled;
use App\Data\Contacts\ContactSourceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteContactSourceRequest;
use App\Http\Requests\DiscoverContactServerRequest;
use App\Http\Requests\IndexContactSourcesRequest;
use App\Http\Requests\PreviewContactSourceRequest;
use App\Http\Requests\SaveContactSourceRequest;
use App\Http\Requests\SyncContactCollectionRequest;
use App\Http\Requests\SyncContactSourceRequest;
use App\Http\Requests\TestContactConnectionRequest;
use App\Http\Requests\UpdateContactCollectionRequest;
use App\Http\Resources\Contacts\ContactSourceResource;
use App\Jobs\SyncContactCollectionJob;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Models\HouseholdMember;
use App\Services\Contacts\ContactProviderRegistry;
use App\Services\Contacts\Providers\CardDavContactProvider;
use App\Support\HouseholdAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class ContactSourceController extends Controller
{
    public function index(IndexContactSourcesRequest $request, ContactProviderRegistry $registry): Response
    {
        $user = $this->authenticatedUser($request);
        $access = app(HouseholdAccess::class);
        $sources = ContactSource::query()
            ->visibleTo($this->authenticatedUser($request))
            ->with('collections')->orderBy('name')->get();

        return Inertia::render('contacts/Sources', [
            'sources' => ContactSourceResource::collection($sources)->resolve($request),
            'providers' => $registry->definitions(),
            'googleDraft' => (function () use ($request): ?array {
                $key = $request->session()->get('google.contact_draft');
                $stored = is_string($key) ? Cache::get('google-contact-draft:'.$key) : null;
                $draft = is_string($stored) ? json_decode(Crypt::decryptString($stored), true) : null;

                return is_array($draft) && $draft['user_id'] === $request->user()->id
                    ? ['email' => $draft['email']] : null;
            })(),
            'households' => $user->households()->whereIn('households.id', $access->memberships($user)->filter(fn (HouseholdMember $member): bool => $access->manages($user, $member->household_id))->pluck('household_id'))->get(['households.id', 'households.name']),
        ]);
    }

    public function store(SaveContactSourceRequest $request, SaveContactSource $action): RedirectResponse
    {
        $source = $action->execute(ContactSourceData::fromArray($request->validated()), $this->authenticatedUser($request));
        if ($source->enabled && $source->sync_enabled) {
            SyncContactSourceJob::dispatch($source->id);
        }

        return redirect()->route('contacts.sources.index');
    }

    public function update(SaveContactSourceRequest $request, ContactSource $source, SaveContactSource $action): RedirectResponse
    {
        $data = $request->validated() + [
            'enabled' => $source->enabled,
            'sync_enabled' => $source->sync_enabled,
        ];
        $sourceData = ContactSourceData::fromArray($data, $source->household_id);
        $requiresSync = $action->requiresSync($source, $sourceData);
        $source = $action->execute($sourceData, $this->authenticatedUser($request), $source);
        if ($source->enabled && $source->sync_enabled && $requiresSync) {
            SyncContactSourceJob::dispatch($source->id);
        }

        return redirect()->route('contacts.sources.index');
    }

    public function destroy(DeleteContactSourceRequest $request, ContactSource $source): RedirectResponse
    {
        if ($source->provider === 'google') {
            $source->update(['encrypted_tokens' => null, 'token_expires_at' => null, 'sync_enabled' => false]);
        }
        $source->delete();

        return redirect()->route('contacts.sources.index');
    }

    public function discoverServer(DiscoverContactServerRequest $request, CardDavContactProvider $provider): JsonResponse
    {
        $validated = $request->validated();

        try {
            $serverUrl = $provider->discoverServerUrl($validated['domain'], $validated['username'], $validated['app_password']);
        } catch (\Exception $exception) {
            report($exception);

            return response()->json(['message' => 'No se encontró un servidor CardDAV en ese dominio. Comprueba el dominio y las credenciales.'], 422);
        }

        return response()->json(['server_url' => $serverUrl]);
    }

    public function preview(PreviewContactSourceRequest $request, ContactProviderRegistry $registry): JsonResponse
    {
        $validated = $request->validated();

        $credentials = $request->credentials();

        $candidate = new ContactSource([
            'provider' => $validated['provider'],
            'provider_configuration' => ['server_url' => $validated['server_url']],
            'encrypted_credentials' => $credentials,
        ]);

        try {
            $collections = $registry->for($candidate)->collections($candidate);
        } catch (\Exception $exception) {
            report($exception);

            return response()->json(['message' => 'No se pudieron descubrir las libretas CardDAV. Revisa la URL y las credenciales.'], 422);
        }

        return response()->json([
            'collections' => array_map(static fn ($collection): array => [
                'remote_id' => $collection->remoteId,
                'name' => $collection->name,
                'read_only' => $collection->readOnly,
            ], $collections),
        ]);
    }

    /**
     * Probe the stored connection of a persisted source.
     *
     * @param  ContactSource  $source  The source whose stored credentials are tested.
     * @param  ContactProviderRegistry  $registry  The registry resolving the provider by key.
     * @return RedirectResponse The back redirect carrying the result toast.
     */
    public function test(SyncContactSourceRequest $request, ContactSource $source, ContactProviderRegistry $registry): RedirectResponse
    {

        return $this->probe($source, $registry);
    }

    /**
     * Probe a connection with the credentials currently typed in the form, without saving.
     *
     * Accepts the same connection fields as the source form so the user can verify the
     * configuration before persisting it. The credentials only live in memory for the duration of
     * the request; nothing is written to the database.
     *
     * @param  ContactProviderRegistry  $registry  The registry resolving the provider by key.
     * @return RedirectResponse The back redirect carrying the result toast.
     */
    public function testConnection(TestContactConnectionRequest $request, ContactProviderRegistry $registry): RedirectResponse
    {
        $validated = $request->validated();

        // Never persisted: instantiated only so the provider can read the same attributes it
        // reads from a stored source.
        $draft = new ContactSource([
            'provider' => $validated['provider'],
            'provider_configuration' => ['server_url' => $validated['server_url']],
            'encrypted_credentials' => [
                'username' => $validated['username'],
                'password' => $validated['app_password'],
            ],
        ]);

        return $this->probe($draft, $registry);
    }

    /**
     * Attempt to list the collections of the given source and report the outcome as a toast.
     *
     * @param  ContactSource  $source  The source, persisted or in-memory, whose connection is tested.
     * @param  ContactProviderRegistry  $registry  The registry resolving the provider by key.
     * @return RedirectResponse The back redirect carrying the result toast.
     */
    private function probe(ContactSource $source, ContactProviderRegistry $registry): RedirectResponse
    {
        try {
            $registry->for($source)->collections($source);
        } catch (\Exception $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $source->provider === 'google'
                    ? 'No se pudo comprobar la conexión con Google Contactos. Vuelve a conectar la cuenta y revisa sus permisos.'
                    : 'No se pudo comprobar la conexión CardDAV. Revisa la URL, las credenciales y la conectividad.',
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $source->provider === 'google'
                ? 'Conexión con Google Contactos correcta.'
                : 'Conexión CardDAV correcta.',
        ]);

        return back();
    }

    public function discover(SyncContactSourceRequest $request, ContactSource $source, DiscoverContactCollections $discover): RedirectResponse
    {

        try {
            $collections = $discover->execute($source);
        } catch (\Exception $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $source->provider === 'google'
                    ? 'No se pudo comprobar la colección de Google Contactos. Vuelve a conectar la cuenta y revisa sus permisos.'
                    : 'No se pudieron descubrir las libretas CardDAV. Comprueba la URL y las credenciales.',
            ]);

            return back();
        }

        $collectionName = $source->provider === 'google' ? 'colecciones de Google Contactos' : 'libretas CardDAV';
        Inertia::flash('toast', [
            'type' => $collections->isEmpty() ? 'info' : 'success',
            'message' => $collections->isEmpty()
                ? 'La conexión funciona, pero no se encontraron '.$collectionName.'.'
                : 'Se encontraron '.$collections->count().' '.$collectionName.'.',
        ]);

        return back();
    }

    public function sync(SyncContactSourceRequest $request, ContactSource $source): RedirectResponse
    {
        SyncContactSourceJob::dispatch($source->id);

        return back();
    }

    public function syncCollection(SyncContactCollectionRequest $request, ContactCollection $collection): RedirectResponse
    {
        SyncContactCollectionJob::dispatch($collection->id);

        return back();
    }

    public function updateCollection(UpdateContactCollectionRequest $request, ContactCollection $collection, SetContactCollectionEnabled $setEnabled): RedirectResponse
    {
        $data = $request->validated();
        $setEnabled->execute($collection, $data['enabled']);

        return back();
    }
}
