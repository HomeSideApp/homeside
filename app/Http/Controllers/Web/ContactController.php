<?php

namespace App\Http\Controllers\Web;

use App\Actions\Contacts\FindContactDuplicates;
use App\Actions\Contacts\MarkFavoriteContacts;
use App\Actions\Contacts\SaveLocalContact;
use App\Actions\Contacts\UseRemoteContactFields;
use App\Data\Contacts\ContactData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveContactRequest;
use App\Http\Resources\Contacts\ContactResource;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\ContactRecord;
use App\Models\ContactSource;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, MarkFavoriteContacts $favorites, FindContactDuplicates $duplicates): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $user = $this->authenticatedUser($request);
        [$search, $labelId] = $this->resolveFilters($request, $user);

        return Inertia::render('contacts/Index', [
            'contacts' => fn (): array => $this->contactsPayload($request, $user, $search, $labelId, $favorites),
            'filters' => ['search' => $search, 'label' => $labelId],
            'duplicate_count' => fn (): int => count($duplicates->execute($user)),
            'labels' => fn (): Collection => $this->labelsQuery($user),
            'households' => fn () => $user->households()->get(['households.id', 'households.name']),
        ]);
    }

    /**
     * Render the household address book using the standard contacts table layout.
     *
     * @param  Request  $request  Incoming web request carrying the search and label filters.
     * @param  Household  $household  Household whose shared contacts are listed.
     * @param  MarkFavoriteContacts  $favorites  Action marking favorite contacts for the viewer.
     * @return Response Inertia response for the household contacts page.
     */
    public function householdIndex(Request $request, Household $household, MarkFavoriteContacts $favorites): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $user = $this->authenticatedUser($request);
        abort_unless($household->isMember($user), 403);
        [$search, $labelId] = $this->resolveFilters($request, $user);

        return Inertia::render('households/contacts/Index', [
            'household' => ['id' => $household->id, 'name' => $household->name],
            'contacts' => fn (): array => $this->contactsPayload($request, $user, $search, $labelId, $favorites, $household->id),
            'filters' => ['search' => $search, 'label' => $labelId],
            'labels' => fn (): Collection => $this->labelsQuery($user),
            'households' => fn () => $user->households()->get(['households.id', 'households.name']),
        ]);
    }

    /**
     * Read the sanitized search term and the resolved label filter from the query string.
     *
     * @param  Request  $request  Incoming web request.
     * @param  User  $user  Authenticated viewer scoping the label lookup.
     * @return array{0: string, 1: string|null} Search term and label id (when the label is visible).
     */
    private function resolveFilters(Request $request, User $user): array
    {
        $searchInput = $request->query('search');
        $search = is_string($searchInput) ? mb_substr(trim($searchInput), 0, 100) : '';
        $requestedLabel = $request->query('label');
        $labelId = is_string($requestedLabel)
            ? ContactLabel::query()->visibleTo($user)->whereKey($requestedLabel)->value('id')
            : null;

        return [$search, $labelId];
    }

    /**
     * Build the contact listing query with its computed primary email and phone columns.
     *
     * @param  User  $user  Authenticated viewer whose visible contacts are listed.
     * @param  string  $search  Sanitized search term matched against name, emails and phones.
     * @param  string|null  $labelId  Optional label restricting the results.
     * @param  string|null  $householdId  Optional household scoping the shared contacts.
     * @return Builder Eloquent query ready to be executed.
     */
    private function contactsQuery(User $user, string $search, ?string $labelId, ?string $householdId = null): Builder
    {
        return Contact::query()->visibleTo($user)
            ->when($householdId !== null, fn (Builder $query): Builder => $query->where('contacts.household_id', $householdId))
            ->with(['labels' => fn (BelongsToMany $labels): BelongsToMany => $labels->visibleTo($user)->orderBy('name')])
            ->when($labelId !== null, fn (Builder $query): Builder => $query->whereHas('labels', fn (Builder $labels): Builder => $labels->whereKey($labelId)->visibleTo($user)))
            ->select('contacts.*')
            ->addSelect([
                'primary_email' => DB::table('contact_emails')
                    ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
                    ->whereColumn('contact_records.contact_id', 'contacts.id')
                    ->whereNull('contact_records.remote_deleted_at')
                    ->where(fn (QueryBuilder $values): QueryBuilder => $values
                        ->whereNull('contact_records.contact_source_id')
                        ->orWhereNotExists(fn (QueryBuilder $local): QueryBuilder => $local
                            ->selectRaw('1')
                            ->from('contact_records as local_records')
                            ->whereColumn('local_records.contact_id', 'contacts.id')
                            ->whereNull('local_records.contact_source_id')
                            ->whereNull('local_records.remote_deleted_at')))
                    ->select('contact_emails.value')
                    ->orderByRaw('CASE WHEN contact_records.contact_source_id IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('contact_records.source_order')->orderBy('contact_records.created_at')
                    ->orderBy('contact_records.id')
                    ->orderByDesc('contact_emails.preferred')
                    ->orderBy('contact_emails.id')
                    ->limit(1),
                'primary_phone' => DB::table('contact_phones')
                    ->join('contact_records', 'contact_records.id', '=', 'contact_phones.contact_record_id')
                    ->whereColumn('contact_records.contact_id', 'contacts.id')
                    ->whereNull('contact_records.remote_deleted_at')
                    ->where(fn (QueryBuilder $values): QueryBuilder => $values
                        ->whereNull('contact_records.contact_source_id')
                        ->orWhereNotExists(fn (QueryBuilder $local): QueryBuilder => $local
                            ->selectRaw('1')
                            ->from('contact_records as local_records')
                            ->whereColumn('local_records.contact_id', 'contacts.id')
                            ->whereNull('local_records.contact_source_id')
                            ->whereNull('local_records.remote_deleted_at')))
                    ->select('contact_phones.value')
                    ->orderByRaw('CASE WHEN contact_records.contact_source_id IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('contact_records.source_order')->orderBy('contact_records.created_at')
                    ->orderBy('contact_records.id')
                    ->orderByDesc('contact_phones.preferred')
                    ->orderBy('contact_phones.id')
                    ->limit(1),
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $pattern = '%'.$search.'%';
                $query->where('contacts.display_name', 'like', $pattern);
                foreach (['emails', 'phones'] as $kind) {
                    $query->orWhereExists(fn (QueryBuilder $values): QueryBuilder => $values
                        ->selectRaw('1')
                        ->from('contact_'.$kind)
                        ->join('contact_records', 'contact_records.id', '=', 'contact_'.$kind.'.contact_record_id')
                        ->whereColumn('contact_records.contact_id', 'contacts.id')
                        ->whereNull('contact_records.remote_deleted_at')
                        ->where('contact_'.$kind.'.value', 'like', $pattern));
                }
            }))
            ->orderBy('display_name')->orderBy('id');
    }

    /**
     * Execute the contact listing query and serialize it for the page props.
     *
     * @param  Request  $request  Incoming web request resolving the resource URLs.
     * @param  User  $user  Authenticated viewer whose favorites are marked.
     * @param  string  $search  Sanitized search term.
     * @param  string|null  $labelId  Optional label restricting the results.
     * @param  MarkFavoriteContacts  $favorites  Action marking favorite contacts.
     * @param  string|null  $householdId  Optional household scoping the shared contacts.
     * @return array<int, mixed> Resolved contact resources.
     */
    private function contactsPayload(Request $request, User $user, string $search, ?string $labelId, MarkFavoriteContacts $favorites, ?string $householdId = null): array
    {
        $contacts = $this->contactsQuery($user, $search, $labelId, $householdId)->get();
        $favorites->execute($contacts, $user);

        return ContactResource::collection($contacts)->resolve($request);
    }

    /**
     * List the labels visible to the viewer with their imported flag.
     *
     * @param  User  $user  Authenticated viewer scoping the labels.
     * @return Collection<int, ContactLabel> Labels ordered by name.
     */
    private function labelsQuery(User $user): Collection
    {
        return ContactLabel::query()->visibleTo($user)
            ->select(['id', 'name', 'user_id'])
            ->withExists(['contacts as imported' => fn (Builder $contacts): Builder => $contacts->where('contact_contact_label.imported', true)])
            ->orderBy('name')->get();
    }

    public function store(SaveContactRequest $request, SaveLocalContact $action): RedirectResponse
    {
        $contact = $action->execute(ContactData::fromArray($request->validated()), $this->authenticatedUser($request), photo: $request->file('photo'), removePhoto: $request->boolean('remove_photo'));

        if ($request->query('back') === 'household' && $contact->household_id !== null) {
            return redirect()->route('households.contacts.index', $contact->household_id);
        }

        return redirect()->route('contacts.index');
    }

    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Contact::class);
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'scope' => ['nullable', 'uuid'],
            'by' => ['nullable', 'in:name,email'],
        ]);

        $query = Contact::query()->visibleTo($this->authenticatedUser($request))
            ->when(isset($validated['scope']), fn ($query) => $query->where('household_id', $validated['scope']))
            ->orderBy('display_name')
            ->limit(20);

        if (($validated['by'] ?? 'name') === 'email') {
            $needle = '%'.$validated['q'].'%';
            $query->whereExists(fn (QueryBuilder $email): QueryBuilder => $email
                ->selectRaw('1')
                ->from('contact_emails')
                ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
                ->whereColumn('contact_records.contact_id', 'contacts.id')
                ->whereNull('contact_records.remote_deleted_at')
                ->where('contact_emails.value', 'like', $needle));
        } else {
            $query->where('display_name', 'like', '%'.$validated['q'].'%');
        }

        $contacts = $query->get();

        $emailsByContact = DB::table('contact_emails')
            ->join('contact_records', 'contact_records.id', '=', 'contact_emails.contact_record_id')
            ->whereIn('contact_records.contact_id', $contacts->pluck('id'))
            ->whereNull('contact_records.remote_deleted_at')
            ->get(['contact_records.contact_id', 'contact_emails.value'])
            ->groupBy('contact_id');

        return response()->json([
            'contacts' => $contacts->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'display_name' => $contact->display_name,
                'household_id' => $contact->household_id,
                'avatar_url' => $contact->preferred_photo_record_id
                    ? route('contacts.avatar', $contact->id)
                    : null,
                'emails' => ($emailsByContact->get($contact->id) ?? collect())
                    ->pluck('value')
                    ->unique()
                    ->values()
                    ->all(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        $user = $this->authenticatedUser($request);
        $contact->load(['records' => fn ($records) => $records->orderBy('source_order')->orderBy('created_at')->orderBy('id'), 'household', 'labels' => fn (BelongsToMany $labels): BelongsToMany => $labels->visibleTo($user)->orderBy('name')]);

        return Inertia::render('contacts/Show', [
            'contact' => (new ContactResource($contact))->resolve($request),
            'household_name' => $contact->household?->name,
            'sources' => ContactSource::query()->withTrashed()->visibleTo($user)
                ->whereIn('id', $contact->records->pluck('contact_source_id')->filter()->unique())
                ->get(['id', 'name']),
            'can' => [
                'update' => Gate::allows('update', $contact),
                'delete' => Gate::allows('delete', $contact),
            ],
        ]);
    }

    public function edit(Request $request, Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        $user = $this->authenticatedUser($request);
        $contact->load(['records' => fn ($records) => $records->orderBy('source_order')->orderBy('created_at')->orderBy('id'), 'labels' => fn (BelongsToMany $labels): BelongsToMany => $labels->visibleTo($user)->orderBy('name')]);

        return Inertia::render('contacts/Edit', [
            'contact' => (new ContactResource($contact))->resolve($request),
            'labels' => ContactLabel::query()->where('user_id', $user->id)->orderBy('name')->get(['id', 'name']),
            'sources' => ContactSource::query()->withTrashed()->visibleTo($user)
                ->whereIn('id', $contact->records->pluck('contact_source_id')->filter()->unique())
                ->get(['id', 'name']),
        ]);
    }

    public function updateLabels(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'label_ids' => ['present', 'array', 'max:100'],
            'label_ids.*' => ['uuid', 'distinct', Rule::exists('contact_labels', 'id')->where('user_id', $user->id)],
        ]);
        $selected = array_fill_keys($validated['label_ids'], true);
        $assigned = $contact->labels()->where('user_id', $user->id)->get();
        foreach ($assigned as $label) {
            if (isset($selected[$label->id])) {
                if (! $label->pivot->manual) {
                    $contact->labels()->updateExistingPivot($label->id, ['manual' => true]);
                }
            } elseif ($label->pivot->imported) {
                $contact->labels()->updateExistingPivot($label->id, ['manual' => false]);
            } else {
                $contact->labels()->detach($label->id);
            }
        }
        foreach (array_keys($selected) as $labelId) {
            if ($assigned->firstWhere('id', $labelId) === null) {
                $contact->labels()->attach($labelId, ['manual' => true, 'imported' => false]);
            }
        }

        return back();
    }

    public function avatar(Contact $contact): \Illuminate\Http\Response
    {
        Gate::authorize('view', $contact);
        $photo = DB::table('contact_record_photos')
            ->join('contact_records', 'contact_records.id', '=', 'contact_record_photos.contact_record_id')
            ->where('contact_records.contact_id', $contact->id)
            ->where('contact_records.id', $contact->preferred_photo_record_id)
            ->first(['contact_record_photos.storage_path']);
        abort_unless($photo && Storage::disk('local')->exists($photo->storage_path), 404);

        return response(Storage::disk('local')->get($photo->storage_path), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function useRemoteFields(Request $request, Contact $contact, UseRemoteContactFields $action): RedirectResponse
    {
        Gate::authorize('update', $contact);
        $validated = $request->validate([
            'record_id' => ['required', 'uuid'],
            'fields' => ['required', 'array', 'min:1', 'max:16'],
            'fields.*' => ['required', 'string', 'distinct', Rule::in(UseRemoteContactFields::FIELDS)],
        ]);
        $remote = ContactRecord::query()->where('contact_id', $contact->id)
            ->whereNotNull('contact_source_id')->findOrFail($validated['record_id']);
        $action->execute($contact, $remote, $validated['fields']);

        return redirect()->route('contacts.show', $contact);
    }

    public function update(SaveContactRequest $request, Contact $contact, SaveLocalContact $action): RedirectResponse
    {
        $action->execute(ContactData::fromArray($request->validated(), $contact->household_id), $this->authenticatedUser($request), $contact, $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('contacts.show', $contact);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return redirect()->route('contacts.index');
    }
}
