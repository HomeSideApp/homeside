<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contacts\DiscoverContactCollections;
use App\Actions\Contacts\SaveContactSource;
use App\Actions\Contacts\SetContactCollectionEnabled;
use App\Data\Contacts\ContactSourceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveContactSourceRequest;
use App\Http\Resources\Contacts\ContactSourceResource;
use App\Jobs\SyncContactCollectionJob;
use App\Jobs\SyncContactSourceJob;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\ContactProviderRegistry;
use App\Services\Contacts\Providers\CardDavContactProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactSourceController extends Controller
{
    public function index(Request $request, ContactProviderRegistry $registry): Response
    {
        Gate::authorize('viewAny', ContactSource::class);
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
            'households' => $this->authenticatedUser($request)->households()->get(['households.id', 'households.name']),
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

    public function destroy(ContactSource $source): RedirectResponse
    {
        Gate::authorize('delete', $source);
        if ($source->provider === 'google') {
            $source->update(['encrypted_tokens' => null, 'token_expires_at' => null, 'sync_enabled' => false]);
        }
        $source->delete();

        return redirect()->route('contacts.sources.index');
    }

    public function discoverServer(Request $request, CardDavContactProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $serverUrl = $provider->discoverServerUrl($validated['domain'], $validated['username'], $validated['app_password']);
        } catch (\Exception $exception) {
            report($exception);

            return response()->json(['message' => 'No se encontró un servidor CardDAV en ese dominio. Comprueba el dominio y las credenciales.'], 422);
        }

        return response()->json(['server_url' => $serverUrl]);
    }

    public function preview(Request $request, ContactProviderRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['carddav'])],
            'source_id' => ['nullable', 'uuid'],
            'server_url' => ['required', 'url:http,https', 'max:2048'],
            'username' => ['nullable', 'string', 'max:255'],
            'app_password' => ['nullable', 'string', 'max:1000'],
        ]);

        $source = null;
        if (isset($validated['source_id'])) {
            $source = ContactSource::query()->findOrFail($validated['source_id']);
            Gate::authorize('update', $source);
        } else {
            Gate::authorize('create', ContactSource::class);
        }

        $existing = $source?->encrypted_credentials ?? [];
        $username = $validated['username'] ?? $existing['username'] ?? null;
        $password = $validated['app_password'] ?? $existing['password'] ?? null;
        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            return response()->json(['message' => 'Introduce el usuario y la contraseña de aplicación.'], 422);
        }

        $candidate = new ContactSource([
            'provider' => $validated['provider'],
            'provider_configuration' => ['server_url' => $validated['server_url']],
            'encrypted_credentials' => ['username' => $username, 'password' => $password],
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
    public function test(ContactSource $source, ContactProviderRegistry $registry): RedirectResponse
    {
        Gate::authorize('sync', $source);

        return $this->probe($source, $registry);
    }

    /**
     * Probe a connection with the credentials currently typed in the form, without saving.
     *
     * Accepts the same connection fields as the source form so the user can verify the
     * configuration before persisting it. The credentials only live in memory for the duration of
     * the request; nothing is written to the database.
     *
     * @param  Request  $request  The incoming HTTP request with the draft connection settings.
     * @param  ContactProviderRegistry  $registry  The registry resolving the provider by key.
     * @return RedirectResponse The back redirect carrying the result toast.
     */
    public function testConnection(Request $request, ContactProviderRegistry $registry): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['carddav'])],
            'server_url' => ['required', 'url:http,https', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'app_password' => ['required', 'string', 'max:1000'],
        ]);

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
                'message' => 'No se pudo comprobar la conexión CardDAV. Revisa la URL, las credenciales y la conectividad.',
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Conexión CardDAV correcta.']);

        return back();
    }

    public function discover(ContactSource $source, DiscoverContactCollections $discover): RedirectResponse
    {
        Gate::authorize('sync', $source);

        try {
            $collections = $discover->execute($source);
        } catch (\Exception $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se pudieron descubrir las libretas CardDAV. Comprueba la URL y las credenciales.',
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => $collections->isEmpty() ? 'info' : 'success',
            'message' => $collections->isEmpty()
                ? 'La conexión funciona, pero no se encontraron libretas CardDAV.'
                : 'Se encontraron '.$collections->count().' libretas CardDAV.',
        ]);

        return back();
    }

    public function sync(ContactSource $source): RedirectResponse
    {
        Gate::authorize('sync', $source);
        SyncContactSourceJob::dispatch($source->id);

        return back();
    }

    public function syncCollection(ContactCollection $collection): RedirectResponse
    {
        Gate::authorize('sync', $collection);
        SyncContactCollectionJob::dispatch($collection->id);

        return back();
    }

    public function updateCollection(Request $request, ContactCollection $collection, SetContactCollectionEnabled $setEnabled): RedirectResponse
    {
        Gate::authorize('update', $collection);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $setEnabled->execute($collection, $data['enabled']);

        return back();
    }
}
