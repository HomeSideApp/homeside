<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Admin\CreateGlobalAiProviderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGlobalAiProviderRequest;
use App\Http\Requests\Admin\UpdateGlobalAiProviderRequest;
use App\Http\Requests\DefaultGlobalAiProviderRequest;
use App\Http\Requests\IndexGlobalAiProvidersRequest;
use App\Http\Requests\ManageGlobalAiProviderRequest;
use App\Http\Requests\TestGlobalAiProviderConfigurationRequest;
use App\Http\Requests\UpdateGlobalModulePromptApiRequest;
use App\Http\Resources\Households\AiProviderResource;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiProviderModel;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AdminAiProviderController extends Controller
{
    public function index(IndexGlobalAiProvidersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $providers = AiProvider::query()->global()->orderBy('name')->orderBy('id')
            ->paginate($validated['perPage'] ?? 15)->withQueryString();

        return AiProviderResource::collection($providers)->response();
    }

    public function store(StoreGlobalAiProviderRequest $request): JsonResponse
    {
        $data = CreateGlobalAiProviderData::fromArray($request->validated());
        $provider = new AiProvider([
            'name' => $data->name, 'type' => $data->driver->value, 'driver' => $data->driver->value,
            'base_url' => $data->base_url, 'model' => $data->model, 'module' => $data->module->value,
            'enabled' => $data->enabled, 'configuration' => $data->configuration,
            'created_by' => $this->authenticatedUser($request)->id,
            'privacy_level' => $data->privacy_level->value, 'fallback_policy' => $data->fallback_policy->value,
        ]);
        $provider->api_key = $data->api_key;
        $provider->save();
        $provider->setModules($data->modules);
        AiProviderModel::create(['ai_provider_id' => $provider->id, 'model' => $data->model, 'display_name' => $data->model, 'enabled' => true, 'is_default' => true]);

        return AiProviderResource::make($provider)->response()->setStatusCode(201);
    }

    public function show(ManageGlobalAiProviderRequest $request, AiProvider $provider): AiProviderResource
    {

        return new AiProviderResource($provider);
    }

    public function update(UpdateGlobalAiProviderRequest $request, AiProvider $provider): AiProviderResource
    {
        $validated = $request->validated();
        $updates = collect($validated)
            ->only(['name', 'base_url', 'model', 'module', 'enabled', 'configuration', 'privacy_level', 'fallback_policy'])
            ->all();
        if (isset($validated['driver']) || isset($validated['type'])) {
            $driver = isset($validated['driver'])
                ? AiDriver::from($validated['driver'])
                : AiDriver::fromLegacyType($validated['type']);
            $updates['driver'] = $driver->value;
            $updates['type'] = $driver->value;
        }
        $provider->update($updates);
        if (isset($validated['modules'])) {
            $provider->setModules($validated['modules']);
        } elseif (isset($validated['module'])) {
            $provider->setModules([$validated['module']]);
        }
        if ($request->filled('api_key')) {
            $provider->api_key = $validated['api_key'];
            $provider->save();
        }

        return new AiProviderResource($provider->fresh());
    }

    public function destroy(ManageGlobalAiProviderRequest $request, AiProvider $provider): Response
    {
        $provider->delete();

        return response()->noContent();
    }

    public function testConfig(TestGlobalAiProviderConfigurationRequest $request, AiProviderTester $tester): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(['data' => $tester->testConfig($request->configuration())]);
    }

    public function test(ManageGlobalAiProviderRequest $request, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {

        return response()->json(['data' => $tester->testProvider($provider)]);
    }

    public function markDefault(DefaultGlobalAiProviderRequest $request, AiProvider $provider): AiProviderResource
    {
        $module = $request->validated()['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        return new AiProviderResource($provider->fresh());
    }

    public function modules(AgentRegistry $registry): JsonResponse
    {
        $modules = collect($registry->getModules())->map(fn ($module, string $key): array => [
            'key' => $key,
            'label' => $module->module()->label(),
            'agents' => collect($module->agents())->map(fn (array $agent, string $name): array => [
                'name' => $name, 'label' => $agent['label'], 'description' => $agent['description'] ?? null,
            ])->values(),
        ])->values();

        return response()->json(['data' => $modules]);
    }

    public function prompts(): JsonResponse
    {
        return response()->json(['data' => AiGlobalSetting::query()->orderBy('module')->get()->map(fn (AiGlobalSetting $setting): array => [
            'module' => $setting->module?->value,
            'extra_prompt' => $setting->extra_prompt,
            'updated_at' => $setting->updated_at?->toISOString(),
        ])]);
    }

    public function updatePrompt(UpdateGlobalModulePromptApiRequest $request, string $module): JsonResponse
    {
        $validated = $request->validated();
        $setting = AiGlobalSetting::updateOrCreate(['module' => $module], ['extra_prompt' => $validated['extra_prompt'] ?? null]);

        return response()->json(['data' => ['module' => $module, 'extra_prompt' => $setting->extra_prompt]]);
    }
}
