<?php

namespace App\Http\Controllers\Web;

use App\Actions\Households\GetHouseholdSettings;
use App\Http\Controllers\Controller;
use App\Models\Household;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\ModelsDev\CatalogPrefill;
use HomeSide\AiAgents\ModelsDev\CatalogQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles catalog search and prefill for AI providers.
 *
 * Each method renders the SAME Inertia page component as the original
 * index controller, but wraps non-search props in closures so they
 * are only evaluated on full page loads. The catalog results are
 * passed as regular props for Inertia partial reloads.
 */
final class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogQuery $catalog,
    ) {}

    /**
     * Search the models.dev catalog from the household AI providers page.
     */
    public function searchProviders(Household $household, Request $request): Response
    {
        $this->authorize('view', $household);

        $term = $request->string('q')->toString();
        $providerSlug = $request->string('provider_slug')->toString();
        $modelId = $request->string('model_id')->toString();

        $catalogPath = route('households.ai-providers.catalog.search', $household);

        // Unified search: when there's a term, search both providers and models.
        $catalogProviders = $term !== ''
            ? $this->catalog->providers($term, perPage: 6)->withPath($catalogPath)->appends(['q' => $term])
            : null;

        $catalogModels = null;
        if ($term !== '') {
            $catalogModels = $this->catalog->models(search: $term, perPage: 15)
                ->withPath($catalogPath)
                ->appends(['q' => $term]);
        } elseif ($providerSlug !== '') {
            $catalogModels = $this->catalog->models(providerSlug: $providerSlug, search: null, perPage: 20);
        }

        $catalogPrefill = null;
        if ($providerSlug !== '' && $modelId !== '') {
            $catalogPrefill = app(CatalogPrefill::class)
                ->prefill($providerSlug, $modelId);
        }

        return Inertia::render('households/settings/AiProviders', [
            'catalogProviders' => $catalogProviders,
            'catalogModels' => $catalogModels,
            'catalogPrefill' => $catalogPrefill,
            'household' => fn () => $household,
            'providers' => fn () => $household->aiProviders()->get()->map(fn (AiProvider $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'driver' => $p->driver ?? $p->type,
                'base_url' => $p->base_url,
                'model' => $p->model,
                'module' => $p->module,
                'modules' => $p->assignedModules(),
                'default_modules' => $p->defaultModules(),
                'module_label' => $p->module,
                'enabled' => $p->enabled,
                'is_default' => $p->is_default,
                'configuration' => $p->configuration,
                'privacy_level' => $p->privacy_level ?? 'unknown',
                'fallback_policy' => $p->fallback_policy ?? 'same_privacy_level',
                'family' => $p->family,
                'description' => $p->description,
                'attachment' => $p->attachment ?? false,
                'reasoning' => $p->reasoning ?? false,
                'reasoning_options' => $p->reasoning_options,
                'tool_call' => $p->tool_call ?? false,
                'structured_output' => $p->structured_output ?? false,
                'temperature' => $p->temperature ?? false,
                'open_weights' => $p->open_weights ?? false,
                'modalities_input' => $p->modalities_input,
                'modalities_output' => $p->modalities_output,
                'context_window' => $p->context_window,
                'max_input_tokens' => $p->max_input_tokens,
                'max_output_tokens' => $p->max_output_tokens,
                'cost_input' => $p->cost_input,
                'cost_output' => $p->cost_output,
                'cost_cache_read' => $p->cost_cache_read,
                'cost_cache_write' => $p->cost_cache_write,
            ]),
            'module_configs' => fn () => $household->moduleConfigurations()->get(),
            'available_modules' => fn () => $this->buildAvailableModules(),
        ]);
    }

    /**
     * Search the models.dev catalog from the admin AI providers page.
     */
    public function searchProvidersAdmin(Request $request): Response
    {
        $term = $request->string('q')->toString();
        $providerSlug = $request->string('provider_slug')->toString();
        $modelId = $request->string('model_id')->toString();

        $catalogPath = route('admin.ai-providers.catalog.search');

        $catalogProviders = $term !== ''
            ? $this->catalog->providers($term, perPage: 6)->withPath($catalogPath)->appends(['q' => $term])
            : null;

        $catalogModels = null;
        if ($term !== '') {
            $catalogModels = $this->catalog->models(search: $term, perPage: 15)
                ->withPath($catalogPath)
                ->appends(['q' => $term]);
        } elseif ($providerSlug !== '') {
            $catalogModels = $this->catalog->models(providerSlug: $providerSlug, search: null, perPage: 20);
        }

        $catalogPrefill = null;
        if ($providerSlug !== '' && $modelId !== '') {
            $catalogPrefill = app(CatalogPrefill::class)
                ->prefill($providerSlug, $modelId);
        }

        return Inertia::render('admin/AiProviders/Index', [
            'catalogProviders' => $catalogProviders,
            'catalogModels' => $catalogModels,
            'catalogPrefill' => $catalogPrefill,
            'providers' => fn () => AiProvider::global()->get()->map(fn (AiProvider $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'driver' => $p->driver ?? $p->type,
                'base_url' => $p->base_url,
                'model' => $p->model,
                'module' => $p->module,
                'modules' => $p->assignedModules(),
                'default_modules' => $p->defaultModules(),
                'module_label' => $p->module,
                'enabled' => $p->enabled,
                'is_default' => $p->is_default,
                'configuration' => $p->configuration,
                'privacy_level' => $p->privacy_level ?? 'unknown',
                'fallback_policy' => $p->fallback_policy ?? 'same_privacy_level',
                'family' => $p->family,
                'description' => $p->description,
                'attachment' => $p->attachment ?? false,
                'reasoning' => $p->reasoning ?? false,
                'reasoning_options' => $p->reasoning_options,
                'tool_call' => $p->tool_call ?? false,
                'structured_output' => $p->structured_output ?? false,
                'temperature' => $p->temperature ?? false,
                'open_weights' => $p->open_weights ?? false,
                'modalities_input' => $p->modalities_input,
                'modalities_output' => $p->modalities_output,
                'context_window' => $p->context_window,
                'max_input_tokens' => $p->max_input_tokens,
                'max_output_tokens' => $p->max_output_tokens,
                'cost_input' => $p->cost_input,
                'cost_output' => $p->cost_output,
                'cost_cache_read' => $p->cost_cache_read,
                'cost_cache_write' => $p->cost_cache_write,
            ]),
            'global_settings' => fn () => AiGlobalSetting::all(),
            'available_modules' => fn () => $this->buildAvailableModules(),
        ]);
    }

    /**
     * Search the models.dev catalog from the personal settings AI providers page.
     */
    public function searchProvidersPersonal(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        $term = $request->string('q')->toString();
        $providerSlug = $request->string('provider_slug')->toString();
        $modelId = $request->string('model_id')->toString();

        $catalogPath = route('settings.ai-providers.catalog.search');

        $catalogProviders = $term !== ''
            ? $this->catalog->providers($term, perPage: 6)->withPath($catalogPath)->appends(['q' => $term])
            : null;

        $catalogModels = null;
        if ($term !== '') {
            $catalogModels = $this->catalog->models(search: $term, perPage: 15)
                ->withPath($catalogPath)
                ->appends(['q' => $term]);
        } elseif ($providerSlug !== '') {
            $catalogModels = $this->catalog->models(providerSlug: $providerSlug, search: null, perPage: 20);
        }

        $catalogPrefill = null;
        if ($providerSlug !== '' && $modelId !== '') {
            $catalogPrefill = app(CatalogPrefill::class)
                ->prefill($providerSlug, $modelId);
        }

        return Inertia::render('settings/AiProviders', [
            'catalogProviders' => $catalogProviders,
            'catalogModels' => $catalogModels,
            'catalogPrefill' => $catalogPrefill,
            'providers' => fn () => AiProvider::query()
                ->whereNull('household_id')
                ->where('user_id', $user->id)
                ->oldest()
                ->get()
                ->map(fn (AiProvider $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'driver' => $p->driver ?? $p->type,
                    'base_url' => $p->base_url,
                    'model' => $p->model,
                    'module' => $p->module,
                    'modules' => $p->assignedModules(),
                    'default_modules' => $p->defaultModules(),
                    'module_label' => $p->module,
                    'enabled' => $p->enabled,
                    'is_default' => $p->is_default,
                    'configuration' => $p->configuration,
                    'privacy_level' => $p->privacy_level ?? 'unknown',
                    'fallback_policy' => $p->fallback_policy ?? 'same_privacy_level',
                    'family' => $p->family,
                    'description' => $p->description,
                    'attachment' => $p->attachment ?? false,
                    'reasoning' => $p->reasoning ?? false,
                    'reasoning_options' => $p->reasoning_options,
                    'tool_call' => $p->tool_call ?? false,
                    'structured_output' => $p->structured_output ?? false,
                    'temperature' => $p->temperature ?? false,
                    'open_weights' => $p->open_weights ?? false,
                    'modalities_input' => $p->modalities_input,
                    'modalities_output' => $p->modalities_output,
                    'context_window' => $p->context_window,
                    'max_input_tokens' => $p->max_input_tokens,
                    'max_output_tokens' => $p->max_output_tokens,
                    'cost_input' => $p->cost_input,
                    'cost_output' => $p->cost_output,
                    'cost_cache_read' => $p->cost_cache_read,
                    'cost_cache_write' => $p->cost_cache_write,
                ]),
        ]);
    }

    /**
     * Search the models.dev catalog from the household wizard page.
     */
    public function searchProvidersWizard(Household $household, Request $request): Response
    {
        $this->authorize('update', $household);

        $term = $request->string('q')->toString();
        $providerSlug = $request->string('provider_slug')->toString();
        $modelId = $request->string('model_id')->toString();

        $catalogPath = route('households.wizard.ai-catalog.search', $household);

        $catalogProviders = $term !== ''
            ? $this->catalog->providers($term, perPage: 6)->withPath($catalogPath)->appends(['q' => $term])
            : null;

        $catalogModels = null;
        if ($term !== '') {
            $catalogModels = $this->catalog->models(search: $term, perPage: 15)
                ->withPath($catalogPath)
                ->appends(['q' => $term]);
        } elseif ($providerSlug !== '') {
            $catalogModels = $this->catalog->models(providerSlug: $providerSlug, search: null, perPage: 20);
        }
        $catalogPrefill = null;
        if ($providerSlug !== '' && $modelId !== '') {
            $catalogPrefill = app(CatalogPrefill::class)
                ->prefill($providerSlug, $modelId);
        }

        $settings = app(GetHouseholdSettings::class)->execute($household);

        return Inertia::render('households/Wizard', [
            'catalogProviders' => $catalogProviders,
            'catalogModels' => $catalogModels,
            'catalogPrefill' => $catalogPrefill,
            'household' => fn () => $settings['household'],
            'modules' => fn () => $settings['modules'],
            'tags' => fn () => $settings['tags'],
            'available_tags' => fn () => $settings['available_tags'],
        ]);
    }

    /**
     * Search models for a specific provider slug.
     */
    public function searchModels(Household $household, Request $request): Response
    {
        $this->authorize('view', $household);

        $providerSlug = $request->string('provider_slug')->toString();
        $term = $request->string('q')->toString();

        $catalogModels = $this->catalog->models(
            providerSlug: $providerSlug,
            search: $term ?: null,
            perPage: 20,
        );

        return Inertia::render('households/settings/AiProviders', [
            'catalogModels' => $catalogModels,
            'household' => fn () => $household,
            'providers' => fn () => [],
            'module_configs' => fn () => [],
            'available_modules' => fn () => $this->buildAvailableModules(),
        ]);
    }

    /**
     * Standalone model search — works from any page via Inertia partial reload.
     *
     * Renders a lightweight page component so the Vue CatalogModelPicker
     * can request only `catalogModels` without re-evaluating heavy props.
     */
    public function searchModelsStandalone(Request $request): Response
    {
        $providerSlug = $request->string('provider_slug')->toString();
        $term = $request->string('q')->toString();

        $catalogModels = $this->catalog->models(
            providerSlug: $providerSlug,
            search: $term ?: null,
            perPage: 20,
        )->withPath(route('catalog.search-models'))
            ->appends(['provider_slug' => $providerSlug, 'q' => $term]);

        // Render the same page the user is currently on.
        // Inertia will only send `catalogModels` back when a partial
        // reload with `only: ['catalogModels']` is used.
        return Inertia::render('catalog/SearchModels', [
            'catalogModels' => $catalogModels,
        ]);
    }

    /**
     * Build the available modules list from the AgentRegistry.
     *
     * @return array<int, array{value: string, label: string, agents: array<int, array{name: string, label: string, description: string|null}>}>
     */
    private function buildAvailableModules(): array
    {
        $registry = app(AgentRegistry::class);

        return collect($registry->getModules())->map(fn ($module, $key) => [
            'value' => $key,
            'label' => __("app.ai_provider_modules.{$key}"),
            'agents' => collect($module->agents())->map(fn ($agent, $agentName) => [
                'name' => $agentName,
                'label' => $agent['label'],
                'description' => $agent['description'] ?? null,
            ])->values()->all(),
        ])->values()->all();
    }
}
