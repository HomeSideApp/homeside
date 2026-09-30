<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Translations\CreateTranslation;
use App\Actions\Translations\DeleteTranslation;
use App\Actions\Translations\GenerateTranslationsWithAi;
use App\Actions\Translations\PublishTranslation;
use App\Actions\Translations\UpdateTranslation;
use App\Data\Translations\CreateTranslationData;
use App\Data\Translations\GenerateTranslationsData;
use App\Data\Translations\PublishTranslationData;
use App\Data\Translations\UpdateTranslationData;
use App\Enums\AppLocale;
use App\Enums\TranslationFieldStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateTranslationsRequest;
use App\Http\Requests\Admin\PublishTranslationRequest;
use App\Http\Requests\Admin\StoreTranslationRequest;
use App\Http\Requests\Admin\UpdateTranslationRequest;
use App\Http\Resources\Translations\TranslationResource;
use App\Http\Resources\Translations\TranslationStatusResource;
use App\Models\Translation;
use App\Models\TranslationStatus;
use HomeSide\AiAgents\Exceptions\NoAiProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Handles the API V1 admin translation CRUD, reusing the same Actions and
 * Requests as the web admin panel.
 *
 * The `search` filter matches against `translations.value` only (searching the
 * source columns of the different entity types is not part of this endpoint).
 */
final class AdminTranslationController extends Controller
{
    /**
     * List translations with optional filters.
     *
     * @return AnonymousResourceCollection The paginated collection of TranslationResource instances.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(['category', 'product', 'store', 'tag'])],
            'locale' => ['sometimes', 'string', Rule::in(AppLocale::values())],
            'field' => ['sometimes', 'string', Rule::in(['name'])],
            'status' => ['sometimes', 'string', Rule::in(TranslationFieldStatus::values())],
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $translations = Translation::query()
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->forType($type))
            ->when($validated['locale'] ?? null, fn ($query, string $locale) => $query->forLocale($locale))
            ->when($validated['field'] ?? null, fn ($query, string $field) => $query->forField($field))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->withStatus(TranslationFieldStatus::from($status)))
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('value', 'like', "%{$search}%"))
            ->orderBy('translatable_type')->orderBy('translatable_id')->orderBy('locale')->orderBy('field')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return TranslationResource::collection($translations);
    }

    /**
     * Create a new translation.
     *
     * @param  StoreTranslationRequest  $request  The validated request with the translation data.
     * @param  CreateTranslation  $action  The action that creates the translation.
     * @return JsonResponse A 201 response with the created TranslationResource, or a 422 validation error.
     */
    public function store(StoreTranslationRequest $request, CreateTranslation $action): JsonResponse
    {
        $data = CreateTranslationData::fromArray($request->validated());
        $translation = $action->execute($data);

        return TranslationResource::make($translation)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing translation.
     *
     * @param  UpdateTranslationRequest  $request  The validated request with the translation data.
     * @param  Translation  $translation  The translation to update.
     * @param  UpdateTranslation  $action  The action that updates the translation.
     * @return JsonResponse The JSON response with the updated TranslationResource.
     */
    public function update(UpdateTranslationRequest $request, Translation $translation, UpdateTranslation $action): JsonResponse
    {
        $data = UpdateTranslationData::fromArray($request->validated());
        $translation = $action->execute($translation, $data);

        return TranslationResource::make($translation)->response();
    }

    /**
     * Delete a translation.
     *
     * @param  Translation  $translation  The translation to delete.
     * @param  DeleteTranslation  $action  The action that deletes the translation.
     * @return Response A 204 no-content response.
     */
    public function destroy(Translation $translation, DeleteTranslation $action): Response
    {
        $action->execute($translation);

        return response()->noContent();
    }

    /**
     * Publish or unpublish the translation of an entity for a locale.
     *
     * @param  PublishTranslationRequest  $request  The validated request with the publish data.
     * @param  PublishTranslation  $action  The action that publishes or unpublishes.
     * @return JsonResponse The JSON response with the resulting TranslationStatusResource.
     */
    public function publish(PublishTranslationRequest $request, PublishTranslation $action): JsonResponse
    {
        $data = PublishTranslationData::fromArray($request->validated());
        $action->execute($data);

        return TranslationStatusResource::make($this->resolveStatus($data->translatableType, $data->translatableId, $data->locale))
            ->response();
    }

    /**
     * Generate translations with the AI agent for the requested type/locale.
     *
     * @param  GenerateTranslationsRequest  $request  The validated request with the generation data.
     * @param  GenerateTranslationsWithAi  $action  The AI generation action.
     * @return JsonResponse A 200 response with {translated, unmatched, failed}, or a 422 when the AI provider is missing or fails.
     */
    public function generate(GenerateTranslationsRequest $request, GenerateTranslationsWithAi $action): JsonResponse
    {
        $data = GenerateTranslationsData::fromArray($request->validated());

        try {
            $result = $action->execute($this->authenticatedUser($request), $data);
        } catch (NoAiProviderException|RuntimeException) {
            return response()->json([
                'message' => __('app.errors.translation_generate_failed', ['message' => '']),
                'code' => 'translation_generation_failed',
                'errors' => (object) [],
            ], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Resolve the fresh translation status row of an entity for a locale.
     */
    private function resolveStatus(string $type, string $id, string $locale): TranslationStatus
    {
        /** @var TranslationStatus $status */
        $status = TranslationStatus::query()
            ->forType($type)
            ->where('translatable_id', $id)
            ->forLocale($locale)
            ->firstOrFail();

        return $status;
    }
}
