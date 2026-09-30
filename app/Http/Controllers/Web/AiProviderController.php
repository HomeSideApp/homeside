<?php

namespace App\Http\Controllers\Web;

use App\Actions\Households\CreateAiProvider;
use App\Actions\Households\MarkAiProviderDefault;
use App\Actions\Households\UpdateAiProvider;
use App\Actions\Households\UpdateModuleAiConfig;
use App\Data\Households\CreateAiProviderData;
use App\Data\Households\UpdateAiProviderData;
use App\Data\Households\UpdateModuleAiConfigData;
use App\Enums\AiProviderModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiProviderRequest;
use App\Http\Requests\UpdateAiProviderRequest;
use App\Http\Requests\UpdateModuleAiConfigRequest;
use App\Models\Household;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\ModuleAiConfiguration;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AiProviderController extends Controller
{
    public function index(Household $household): Response
    {
        $this->authorize('view', $household);

        $providers = $household->aiProviders()->get()->map(fn (AiProvider $p) => [
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
            // Catalog-aligned spec columns.
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
        ]);

        $this->syncModuleConfigs($household);
        $moduleConfigs = $household->moduleConfigurations()->get();

        $registry = app(AgentRegistry::class);
        $availableModules = collect($registry->getModules())->map(fn ($module, $key) => [
            'value' => $key,
            'label' => __("app.ai_provider_modules.{$key}"),
            'agents' => collect($module->agents())->map(fn ($agent, $agentName) => [
                'name' => $agentName,
                'label' => $agent['label'],
                'description' => $agent['description'] ?? null,
            ])->values()->all(),
        ])->values()->all();

        return Inertia::render('households/settings/AiProviders', [
            'household' => $household,
            'providers' => $providers,
            'module_configs' => $moduleConfigs,
            'available_modules' => $availableModules,
        ]);
    }

    public function store(StoreAiProviderRequest $request, Household $household, CreateAiProvider $action): RedirectResponse
    {
        $data = CreateAiProviderData::fromArray($request->validated());
        $action->execute($data, $household, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA creado correctamente.']);

        // Redirect to wizard if coming from there, otherwise back
        $backUrl = $request->input('back_url');
        if ($backUrl) {
            return redirect($backUrl);
        }

        return back();
    }

    public function update(UpdateAiProviderRequest $request, Household $household, AiProvider $provider, UpdateAiProvider $action): RedirectResponse
    {
        $data = UpdateAiProviderData::fromArray($request->validated());
        $action->execute($provider, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA actualizado.']);

        return back();
    }

    public function destroy(Household $household, AiProvider $provider): RedirectResponse
    {
        $this->authorize('manage', $provider);
        $provider->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA eliminado.']);

        return back();
    }

    public function test(Household $household, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {
        $this->authorize('manage', $provider);

        return response()->json($tester->testProvider($provider));
    }

    public function markDefault(Household $household, AiProvider $provider, MarkAiProviderDefault $action): RedirectResponse
    {
        $this->authorize('manage', $provider);
        $module = request()->validate(['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]])['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor marcado como predeterminado.']);

        return back();
    }

    public function updateModuleConfig(Household $household, UpdateModuleAiConfigRequest $request, UpdateModuleAiConfig $action): RedirectResponse
    {
        $this->authorize('manage', $household);

        $data = UpdateModuleAiConfigData::fromArray($request->validated());
        $action->execute($household, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.agent_config_updated')]);

        return back();
    }

    public function testConfig(Household $household, AiProviderTester $tester, Request $request): JsonResponse
    {
        $this->authorize('view', $household);

        return response()->json($tester->testConfig([
            'type' => $request->string('type', 'openai-compatible')->toString(),
            'base_url' => $request->string('base_url')->toString(),
            'model' => $request->string('model')->toString(),
            'api_key' => $request->string('api_key')->toString(),
        ]));
    }

    private function syncModuleConfigs(Household $household): void
    {
        $registry = app(AgentRegistry::class);

        foreach ($registry->getModules() as $moduleName => $moduleProvider) {
            foreach ($moduleProvider->agents() as $agentName => $agentConfig) {
                ModuleAiConfiguration::firstOrCreate(
                    [
                        'household_id' => $household->id,
                        'module' => $moduleName,
                        'agent_name' => $agentName,
                    ],
                    [
                        'label' => $agentConfig['label'],
                        'system_prompt' => $agentConfig['system_prompt'],
                        'additional_instructions' => null,
                        'description' => $agentConfig['description'] ?? null,
                        'model' => null,
                        'parameters' => $agentConfig['parameters'] ?? null,
                        'enabled' => true,
                    ]
                );
            }
        }
    }
}
