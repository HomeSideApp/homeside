<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Http\Resources\Households\AiProviderResource;
use App\Models\Household;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Handles the API household AI provider CRUD.
 */
final class AiProviderController extends Controller
{
    /**
     * List a household's AI providers.
     *
     * @param  Household  $household  The household whose AI providers are listed.
     * @return AnonymousResourceCollection The collection of AiProviderResource instances for the household.
     */
    public function index(Household $household): AnonymousResourceCollection
    {
        $this->authorize('view', $household);

        return AiProviderResource::collection($household->aiProviders);
    }

    /**
     * Create a household AI provider.
     *
     * @param  StoreAiProviderRequest  $request  The validated request with the provider data.
     * @param  Household  $household  The household the provider belongs to.
     * @param  CreateAiProvider  $action  The action that creates the provider.
     * @return JsonResponse The JSON response with the created AiProviderResource.
     */
    public function store(StoreAiProviderRequest $request, Household $household, CreateAiProvider $action): JsonResponse
    {
        $this->authorize('manage', $household);
        $data = CreateAiProviderData::fromArray($request->validated());
        $provider = $action->execute($data, $household, $this->authenticatedUser($request));

        return AiProviderResource::make($provider)->response()->setStatusCode(201);
    }

    /**
     * Show a household AI provider.
     *
     * @param  Household  $household  The household the provider belongs to.
     * @param  AiProvider  $provider  The AI provider to display.
     * @return AiProviderResource The resource representation of the provider.
     */
    public function show(Household $household, AiProvider $provider): AiProviderResource
    {
        $provider = $this->providerForHousehold($household, $provider);
        $this->authorize('view', $provider);

        return new AiProviderResource($provider);
    }

    /**
     * Update a household AI provider.
     *
     * @param  UpdateAiProviderRequest  $request  The validated request with the provider data.
     * @param  Household  $household  The household the provider belongs to.
     * @param  AiProvider  $provider  The AI provider to update.
     * @param  UpdateAiProvider  $action  The action that updates the provider.
     * @return AiProviderResource The updated resource representation of the provider.
     */
    public function update(UpdateAiProviderRequest $request, Household $household, AiProvider $provider, UpdateAiProvider $action): AiProviderResource
    {
        $provider = $this->providerForHousehold($household, $provider);
        $this->authorize('manage', $provider);
        $data = UpdateAiProviderData::fromArray($request->validated());

        return new AiProviderResource($action->execute($provider, $data));
    }

    /**
     * Delete a household AI provider.
     *
     * @param  Household  $household  The household the provider belongs to.
     * @param  AiProvider  $provider  The AI provider to delete.
     * @return Response An empty 204 no-content response.
     */
    public function destroy(Household $household, AiProvider $provider): Response
    {
        $provider = $this->providerForHousehold($household, $provider);
        $this->authorize('manage', $provider);
        $provider->delete();

        return response()->noContent();
    }

    /**
     * Test a household AI provider connection.
     *
     * @param  Household  $household  The household the provider belongs to.
     * @param  AiProvider  $provider  The AI provider to test.
     * @param  AiProviderTester  $tester  The service that tests the provider connection.
     * @return JsonResponse The JSON response with the connection test result.
     */
    public function test(Household $household, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {
        $provider = $this->providerForHousehold($household, $provider);
        $this->authorize('manage', $provider);

        return response()->json($tester->testProvider($provider));
    }

    /**
     * Test an unsaved provider configuration for a household.
     */
    public function testConfig(Household $household, Request $request, AiProviderTester $tester): JsonResponse
    {
        $this->authorize('manage', $household);

        $configuration = $request->validate([
            'type' => ['required', 'string', Rule::in(AiDriver::cases())],
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($tester->testConfig($configuration));
    }

    /**
     * Mark a household provider as the default for its module.
     */
    public function markDefault(
        Household $household,
        AiProvider $provider,
        MarkAiProviderDefault $action,
    ): AiProviderResource {
        $provider = $this->providerForHousehold($household, $provider);
        $this->authorize('manage', $provider);
        $module = request()->validate(['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]])['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        return new AiProviderResource($provider->refresh());
    }

    /**
     * Update an AI agent configuration for a household module.
     */
    public function updateModuleConfig(
        Household $household,
        UpdateModuleAiConfigRequest $request,
        UpdateModuleAiConfig $action,
    ): JsonResponse {
        $this->authorize('manage', $household);
        $configuration = $action->execute(
            $household,
            UpdateModuleAiConfigData::fromArray($request->validated()),
        );

        return response()->json(['data' => $configuration]);
    }

    /**
     * Ensure a provider belongs to the household represented by the route.
     */
    private function providerForHousehold(Household $household, AiProvider $provider): AiProvider
    {
        abort_unless($provider->household_id === $household->id && $provider->user_id === null, 404);

        return $provider;
    }
}
