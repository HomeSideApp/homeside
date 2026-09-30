<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Admin\CreateGlobalAiProviderData;
use App\Enums\AiProviderModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGlobalAiProviderRequest;
use App\Http\Requests\Admin\UpdateGlobalAiProviderRequest;
use App\Http\Resources\Households\AiProviderResource;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiProviderModel;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AdminAiProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
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

    public function show(AiProvider $provider): AiProviderResource
    {
        $this->ensureGlobal($provider);

        return new AiProviderResource($provider);
    }

    public function update(UpdateGlobalAiProviderRequest $request, AiProvider $provider): AiProviderResource
    {
        $this->ensureGlobal($provider);
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

    public function destroy(AiProvider $provider): Response
    {
        $this->ensureGlobal($provider);
        $provider->delete();

        return response()->noContent();
    }

    public function testConfig(Request $request, AiProviderTester $tester): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string'], 'base_url' => ['required', 'url'],
            'model' => ['required', 'string'], 'api_key' => ['required', 'string'],
        ]);

        return response()->json(['data' => $tester->testConfig($validated)]);
    }

    public function test(AiProvider $provider, AiProviderTester $tester): JsonResponse
    {
        $this->ensureGlobal($provider);

        return response()->json(['data' => $tester->testProvider($provider)]);
    }

    public function markDefault(Request $request, AiProvider $provider): AiProviderResource
    {
        $this->ensureGlobal($provider);
        $module = $request->validate(['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]])['module'] ?? $provider->module;
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

    public function updatePrompt(Request $request, string $module): JsonResponse
    {
        abort_unless(in_array($module, array_column(AiProviderModule::cases(), 'value'), true), 404);
        $validated = $request->validate(['extra_prompt' => ['nullable', 'string', 'max:20000']]);
        $setting = AiGlobalSetting::updateOrCreate(['module' => $module], ['extra_prompt' => $validated['extra_prompt'] ?? null]);

        return response()->json(['data' => ['module' => $module, 'extra_prompt' => $setting->extra_prompt]]);
    }

    private function ensureGlobal(AiProvider $provider): void
    {
        abort_unless($provider->isGlobal(), 404);
    }
}
