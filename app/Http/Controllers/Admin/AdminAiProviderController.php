<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Data\Admin\CreateGlobalAiProviderData;
use App\Data\Admin\UpdateGlobalAiProviderData;
use App\Data\Admin\UpdateGlobalPromptData;
use App\Data\Admin\UpdateModulePromptData;
use App\Enums\AiProviderModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGlobalAiProviderRequest;
use App\Http\Requests\Admin\UpdateGlobalAiProviderRequest;
use App\Http\Requests\Admin\UpdateGlobalPromptRequest;
use App\Http\Requests\Admin\UpdateModulePromptRequest;
use App\Http\Requests\ProviderSpecificationRules;
use App\Models\User;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiProviderModel;
use HomeSide\AiAgents\Providers\AiProviderTester;
use HomeSide\AiAgents\Providers\ImageGenerationProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminAiProviderController extends Controller
{
    public function index(): Response
    {
        $providers = AiProvider::global()->get()->map(fn (AiProvider $p) => [
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

        $globalSettings = AiGlobalSetting::all();

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

        return Inertia::render('admin/AiProviders/Index', [
            'providers' => $providers,
            'global_settings' => $globalSettings,
            'available_modules' => $availableModules,
        ]);
    }

    public function store(StoreGlobalAiProviderRequest $request): RedirectResponse
    {
        $data = CreateGlobalAiProviderData::fromArray($request->validated());
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $provider = new AiProvider([
            'name' => $data->name,
            'type' => $data->driver->value,
            'driver' => $data->driver->value,
            'base_url' => $data->base_url,
            'model' => $data->model,
            'module' => $data->module->value,
            'enabled' => $data->enabled,
            'configuration' => $data->configuration,
            'created_by' => $user->id,
            'privacy_level' => $data->privacy_level->value,
            'fallback_policy' => $data->fallback_policy->value,
            ...ProviderSpecificationRules::attributes($request->validated()),
        ]);

        $provider->api_key = $data->api_key;
        $provider->save();
        $provider->setModules($data->modules);

        // Crear AiProviderModel inicial
        AiProviderModel::create([
            'ai_provider_id' => $provider->id,
            'model' => $data->model,
            'display_name' => $data->model,
            'enabled' => true,
            'is_default' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA global creado correctamente.']);

        return back();
    }

    public function update(UpdateGlobalAiProviderRequest $request, AiProvider $provider): RedirectResponse
    {
        $this->authorize('manage', $provider);

        $data = UpdateGlobalAiProviderData::fromArray($request->validated());

        $provider->update([
            'name' => $data->name,
            'type' => $data->driver->value,
            'driver' => $data->driver->value,
            'base_url' => $data->base_url,
            'model' => $data->model,
            'module' => $data->module->value,
            'enabled' => $data->enabled,
            'configuration' => $data->configuration,
            'privacy_level' => $data->privacy_level->value,
            'fallback_policy' => $data->fallback_policy->value,
            ...ProviderSpecificationRules::attributes($request->validated()),
        ]);
        $provider->setModules($data->modules);

        if ($request->filled('api_key')) {
            $provider->api_key = $data->api_key;
            $provider->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA global actualizado.']);

        return back();
    }

    public function destroy(AiProvider $provider): RedirectResponse
    {
        $this->authorize('manage', $provider);
        $provider->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor IA global eliminado.']);

        return back();
    }

    public function test(AiProvider $provider, AiProviderTester $tester): JsonResponse
    {
        $this->authorize('manage', $provider);

        return response()->json($tester->testProvider($provider));
    }

    public function markDefault(AiProvider $provider): RedirectResponse
    {
        $this->authorize('manage', $provider);
        $module = request()->validate(['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]])['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proveedor marcado como predeterminado.']);

        return back();
    }

    public function testConfig(
        AiProviderTester $tester,
        ImageGenerationProviderTester $imageTester,
    ): JsonResponse {
        $module = AiProviderModule::tryFrom(request('module', ''));

        $config = [
            'type' => request('type'),
            'base_url' => request('base_url'),
            'model' => request('model'),
            'api_key' => request('api_key'),
        ];

        if ($module === AiProviderModule::ImageGeneration) {
            return response()->json($imageTester->testConfig($config));
        }

        return response()->json($tester->testConfig($config));
    }

    public function updateGlobalPrompt(UpdateGlobalPromptRequest $request): RedirectResponse
    {
        $data = UpdateGlobalPromptData::fromArray($request->validated());
        AiGlobalSetting::updateOrCreate(
            ['module' => null],
            ['extra_prompt' => $data->extra_prompt]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Prompt global actualizado.']);

        return back();
    }

    public function updateModulePrompt(UpdateModulePromptRequest $request): RedirectResponse
    {
        $data = UpdateModulePromptData::fromArray($request->validated());
        AiGlobalSetting::updateOrCreate(
            ['module' => $data->module->value],
            ['extra_prompt' => $data->extra_prompt]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.module_prompt_updated')]);

        return back();
    }
}
