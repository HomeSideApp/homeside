<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Households\CreateAiProvider;
use App\Actions\Households\MarkAiProviderDefault;
use App\Actions\Households\UpdateAiProvider;
use App\Actions\Households\UpdateModuleAiConfig;
use App\Data\Households\CreateAiProviderData;
use App\Data\Households\UpdateAiProviderData;
use App\Data\Households\UpdateModuleAiConfigData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DefaultHouseholdAiProviderRequest;
use App\Http\Requests\ManageHouseholdAiProviderRequest;
use App\Http\Requests\StoreAiProviderRequest;
use App\Http\Requests\TestHouseholdAiProviderConfigurationRequest;
use App\Http\Requests\UpdateAiProviderRequest;
use App\Http\Requests\UpdateModuleAiConfigRequest;
use App\Http\Requests\ViewHouseholdAiProviderRequest;
use App\Http\Requests\ViewHouseholdRequest;
use App\Http\Resources\Households\AiProviderResource;
use App\Models\Household;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
    public function index(ViewHouseholdRequest $request, Household $household): AnonymousResourceCollection
    {

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
    public function show(ViewHouseholdAiProviderRequest $request, Household $household, AiProvider $provider): AiProviderResource
    {

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
    public function destroy(ManageHouseholdAiProviderRequest $request, Household $household, AiProvider $provider): Response
    {
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
    public function test(ManageHouseholdAiProviderRequest $request, Household $household, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {

        return response()->json($tester->testProvider($provider));
    }

    /**
     * Test an unsaved provider configuration for a household.
     */
    public function testConfig(Household $household, TestHouseholdAiProviderConfigurationRequest $request, AiProviderTester $tester): JsonResponse
    {

        $configuration = $request->configuration();

        return response()->json($tester->testConfig($configuration));
    }

    /**
     * Mark a household provider as the default for its module.
     */
    public function markDefault(DefaultHouseholdAiProviderRequest $request,
        Household $household,
        AiProvider $provider,
        MarkAiProviderDefault $action,
    ): AiProviderResource {
        $module = $request->validated()['module'] ?? $provider->module;
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
        $configuration = $action->execute(
            $household,
            UpdateModuleAiConfigData::fromArray($request->validated()),
        );

        return response()->json(['data' => $configuration]);
    }
}
