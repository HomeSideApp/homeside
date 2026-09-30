<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Translations\CreateTranslation;
use App\Actions\Translations\DeleteTranslation;
use App\Actions\Translations\GenerateTranslationsWithAi;
use App\Actions\Translations\PublishTranslation;
use App\Actions\Translations\UpdateTranslation;
use App\Concerns\TranslationLocaleRules;
use App\Data\Translations\CreateTranslationData;
use App\Data\Translations\GenerateTranslationsData;
use App\Data\Translations\PublishTranslationData;
use App\Data\Translations\UpdateTranslationData;
use App\Enums\AppLocale;
use App\Enums\TranslationEntityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkPublishTranslationsRequest;
use App\Http\Requests\Admin\GenerateTranslationsRequest;
use App\Http\Requests\Admin\PublishTranslationRequest;
use App\Http\Requests\Admin\StoreTranslationRequest;
use App\Http\Requests\Admin\UpdateTranslationRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\TranslationStatus;
use HomeSide\AiAgents\Exceptions\NoAiProviderException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Handles the admin translations panel: browse translatable catalog
 * entities, edit their translations inline and publish/unpublish them.
 *
 * The controller reuses the existing Translations Actions and DTOs; the
 * catalog source names are shown untranslated (this is an admin surface).
 */
final class AdminTranslationController extends Controller
{
    /**
     * Morph map aliases of the translatable catalog models.
     *
     * @var list<string>
     */
    private const ALLOWED_TYPES = ['category', 'product', 'store', 'tag'];

    /**
     * List translatable entities of the selected type with their translation
     * coverage for the target locale.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Request $request): Response
    {
        $type = $this->resolveType($request->input('type'));
        $locale = $this->resolveLocale($request->input('locale'));
        $status = $this->resolveStatus($request->input('status'));
        $search = (string) $request->input('search', '');
        $perPage = max(1, (int) $request->input('perPage', 10));

        $sourceLocale = AppLocale::resolve(config('app.fallback_locale'))->value;
        $targetLocales = array_values(array_unique([
            ...array_diff(AppLocale::values(), [$sourceLocale]),
            $locale,
        ]));

        $query = $this->translatableQuery($type)->withTranslationData();

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $query = $this->applyStatusFilter($query, $status, $locale);

        $entities = $query->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Model $entity): array => $this->buildEntityRow(
                $entity,
                $locale,
                $sourceLocale,
                $targetLocales,
            ));

        return Inertia::render('admin/Translations/Index', [
            'entities' => $entities,
            'filters' => [
                'type' => $type,
                'locale' => $locale,
                'status' => $status,
                'search' => $search === '' ? null : $search,
                'perPage' => $perPage,
            ],
            'supportedLocales' => TranslationLocaleRules::supportedTranslationLocales($locale),
            'sourceLocale' => $sourceLocale,
            'counts' => $this->buildCounts($type, $locale),
        ]);
    }

    /**
     * Create or update a field translation for an entity.
     *
     * @param  StoreTranslationRequest  $request  The validated request.
     * @param  CreateTranslation  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreTranslationRequest $request, CreateTranslation $action): RedirectResponse
    {
        $data = CreateTranslationData::fromArray($request->validated());
        $action->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.translation_saved')]);

        return back();
    }

    /**
     * Update an existing field translation.
     *
     * @param  UpdateTranslationRequest  $request  The validated request.
     * @param  Translation  $translation  The translation model instance.
     * @param  UpdateTranslation  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateTranslationRequest $request, Translation $translation, UpdateTranslation $action): RedirectResponse
    {
        $data = UpdateTranslationData::fromArray($request->validated());
        $action->execute($translation, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.translation_saved')]);

        return back();
    }

    /**
     * Delete a field translation.
     *
     * @param  Translation  $translation  The translation model instance.
     * @param  DeleteTranslation  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Translation $translation, DeleteTranslation $action): RedirectResponse
    {
        $action->execute($translation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.translation_deleted')]);

        return back();
    }

    /**
     * Publish or unpublish the translation of an entity for a locale.
     *
     * @param  PublishTranslationRequest  $request  The validated request.
     * @param  PublishTranslation  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function publish(PublishTranslationRequest $request, PublishTranslation $action): RedirectResponse
    {
        $data = PublishTranslationData::fromArray($request->validated());
        $action->execute($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data->publish
                ? __('app.toast.translation_published')
                : __('app.toast.translation_unpublished'),
        ]);

        return back();
    }

    /**
     * Publish complete translations from selected rows or every filtered result.
     */
    public function publishBulk(BulkPublishTranslationsRequest $request, PublishTranslation $action): RedirectResponse
    {
        $data = $request->validated();
        $type = $data['translatable_type'];
        $locale = TranslationLocaleRules::normalizeLocaleTag($data['locale']);

        $query = $this->translatableQuery($type)->withTranslationData();

        if ($data['mode'] === 'selected') {
            $query->whereKey($data['ids']);
        } else {
            $search = $data['search'] ?? '';

            if ($search !== '') {
                $query->where('name', 'like', "%{$search}%");
            }

            $this->applyStatusFilter($query, $data['status'] ?? 'all', $locale);
        }

        $published = 0;
        $skipped = 0;

        $query->chunkById(100, function ($entities) use ($action, $type, $locale, &$published, &$skipped): void {
            foreach ($entities as $entity) {
                if ($entity->isPublished($locale) || ! $entity->isComplete($locale)) {
                    $skipped++;

                    continue;
                }

                $action->execute(new PublishTranslationData(
                    translatableType: $type,
                    translatableId: (string) $entity->getKey(),
                    locale: $locale,
                    publish: true,
                ));
                $published++;
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.translations_bulk_published', [
                'published' => $published,
                'skipped' => $skipped,
            ]),
        ]);

        return back();
    }

    /**
     * Generate translations with the AI agent for the requested type/locale.
     *
     * @param  GenerateTranslationsRequest  $request  The validated request.
     * @param  GenerateTranslationsWithAi  $action  The AI generation action.
     * @return RedirectResponse The HTTP response.
     */
    public function generate(GenerateTranslationsRequest $request, GenerateTranslationsWithAi $action): RedirectResponse
    {
        $data = GenerateTranslationsData::fromArray($request->validated());

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        try {
            $result = $action->execute($user, $data);
        } catch (NoAiProviderException|RuntimeException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.errors.translation_generate_failed', ['message' => $e->getMessage()]),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.translations_generated', ['count' => $result['translated']]),
        ]);

        return back();
    }

    /**
     * Create a query builder for a translatable entity type.
     *
     * @param  string  $type  The morph map alias of the entity type.
     * @return Builder<Category>|Builder<Product>|Builder<Store>|Builder<Tag>
     */
    private function translatableQuery(string $type): Builder
    {
        return match ($type) {
            'category' => Category::query(),
            'product' => Product::query(),
            'store' => Store::query(),
            'tag' => Tag::query(),
            default => abort(404, "Unknown translatable type [{$type}]."),
        };
    }

    /**
     * Restrict the entity type to the known translatable morph aliases.
     */
    private function resolveType(?string $type): string
    {
        return in_array($type, self::ALLOWED_TYPES, true) ? (string) $type : self::ALLOWED_TYPES[0];
    }

    /**
     * Accept catalog translation locales independently of the application UI locales.
     */
    private function resolveLocale(?string $locale): string
    {
        return TranslationLocaleRules::normalizeLocaleTag((string) $locale)
            ?? AppLocale::SpanishSpain->value;
    }

    /**
     * Restrict the status filter to the supported values.
     */
    private function resolveStatus(?string $status): string
    {
        return in_array($status, ['all', 'incomplete', 'complete', 'published', 'untranslated'], true)
            ? (string) $status
            : 'all';
    }

    /**
     * Apply the status filter to the entity query via the polymorphic
     * translation statuses (or the absence of translations).
     *
     * @param  Builder<Category>|Builder<Product>|Builder<Store>|Builder<Tag>  $query
     * @param  string  $status  The requested status filter.
     * @param  string  $locale  The target locale of the filter.
     * @return Builder<Category>|Builder<Product>|Builder<Store>|Builder<Tag>
     */
    private function applyStatusFilter(Builder $query, string $status, string $locale): Builder
    {
        return match ($status) {
            'incomplete' => $query->whereHas(
                'translationStatus',
                fn (Builder $q): Builder => $q->where('locale', $locale)->where(
                    'status',
                    TranslationEntityStatus::Incomplete,
                ),
            ),
            'complete' => $query->whereHas(
                'translationStatus',
                fn (Builder $q): Builder => $q->where('locale', $locale)->where(
                    'status',
                    TranslationEntityStatus::Complete,
                ),
            ),
            'published' => $query->whereHas(
                'translationStatus',
                fn (Builder $q): Builder => $q->where('locale', $locale)->where(
                    'status',
                    TranslationEntityStatus::Published,
                ),
            ),
            'untranslated' => $query->whereDoesntHave(
                'translations',
                fn (Builder $q): Builder => $q->where('locale', $locale),
            ),
            default => $query,
        };
    }

    /**
     * Build a single serializable row for the entities prop.
     *
     * @param  string  $locale  The target locale of the row.
     * @param  string  $sourceLocale  The source (fallback) locale.
     * @param  list<string>  $targetLocales  Locales shown in the row.
     * @return array{id: string, type: string, name: string|null, translations: array<string, array{id: string, value: string, status: string}|null>, status: string|null, published_at: string|null, is_complete: bool, coverage: array{completed: int, total: int}, translation_id: string|null, field_status: string|null}
     */
    private function buildEntityRow(Category|Product|Store|Tag $entity, string $locale, string $sourceLocale, array $targetLocales): array
    {
        $translationsByLocale = [];

        foreach ($targetLocales as $target) {
            $row = $entity->translations->first(fn (Translation $t): bool => $t->locale === $target);

            $translationsByLocale[$target] = $row === null ? null : [
                'id' => $row->id,
                'value' => $row->value,
                'status' => $row->status->value,
            ];
        }

        $statusRow = $entity->translationStatus->first(
            fn (TranslationStatus $s): bool => $s->locale === $locale,
        );

        $localeTranslations = $entity->translations->filter(
            fn (Translation $t): bool => $t->locale === $locale,
        )->keyBy('field');

        $fields = $entity->getTranslatableAttributes();
        $completed = collect($fields)
            ->filter(fn (string $field): bool => trim((string) ($localeTranslations[$field]->value ?? '')) !== '')
            ->count();

        $localeTranslation = $localeTranslations['name'] ?? null;

        return [
            'id' => (string) $entity->getKey(),
            'type' => $entity->translatableType(),
            'name' => $entity->getAttribute('name'),
            'translations' => $translationsByLocale,
            'status' => $statusRow?->status?->value,
            'published_at' => $statusRow?->published_at?->toISOString(),
            'is_complete' => $entity->isComplete($locale),
            'coverage' => [
                'completed' => $completed,
                'total' => count($fields),
            ],
            'translation_id' => $localeTranslation?->id,
            'field_status' => $localeTranslation?->status?->value,
        ];
    }

    /**
     * Aggregate coverage counts for the header, filtered by type and locale.
     *
     * @return array{incomplete: int, complete: int, published: int, untranslated: int}
     */
    private function buildCounts(string $type, string $locale): array
    {
        $statusCounts = TranslationStatus::query()
            ->forType($type)
            ->forLocale($locale)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalEntities = (int) $this->translatableQuery($type)->count();
        $incomplete = (int) ($statusCounts['incomplete'] ?? 0);
        $complete = (int) ($statusCounts['complete'] ?? 0);
        $published = (int) ($statusCounts['published'] ?? 0);

        return [
            'incomplete' => $incomplete,
            'complete' => $complete,
            'published' => $published,
            'untranslated' => max(0, $totalEntities - ($incomplete + $complete + $published)),
        ];
    }
}
