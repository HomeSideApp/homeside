<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\CreateUserAiProvider;
use App\Actions\Households\MarkAiProviderDefault;
use App\Actions\Households\UpdateAiProvider;
use App\Data\Households\CreateAiProviderData;
use App\Data\Households\UpdateAiProviderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DefaultUserAiProviderRequest;
use App\Http\Requests\ManageUserAiProviderRequest;
use App\Http\Requests\Settings\StoreUserAiProviderRequest;
use App\Http\Requests\Settings\UpdateUserAiProviderRequest;
use App\Http\Requests\TestUserAiProviderConfigurationRequest;
use App\Http\Resources\Households\AiProviderResource;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Handles API operations for AI providers owned by the authenticated user.
 */
final class UserAiProviderController extends Controller
{
    /**
     * List the authenticated user's personal providers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return AiProviderResource::collection(
            AiProvider::query()
                ->whereNull('household_id')
                ->where('user_id', $this->authenticatedUser($request)->id)
                ->oldest()
                ->get(),
        );
    }

    /**
     * Create a personal provider.
     */
    public function store(StoreUserAiProviderRequest $request, CreateUserAiProvider $action): JsonResponse
    {
        $provider = $action->execute(
            CreateAiProviderData::fromArray($request->validated()),
            $this->authenticatedUser($request),
        );

        return AiProviderResource::make($provider)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a personal provider.
     */
    public function update(
        UpdateUserAiProviderRequest $request,
        AiProvider $provider,
        UpdateAiProvider $action,
    ): AiProviderResource {
        $data = UpdateAiProviderData::fromArray($request->validated());

        return new AiProviderResource($action->execute($provider, $data));
    }

    /**
     * Delete a personal provider.
     */
    public function destroy(ManageUserAiProviderRequest $request, AiProvider $provider): Response
    {
        $provider->delete();

        return response()->noContent();
    }

    /**
     * Mark a personal provider as default for its module.
     */
    public function markDefault(
        DefaultUserAiProviderRequest $request,
        AiProvider $provider,
        MarkAiProviderDefault $action,
    ): AiProviderResource {
        $module = $request->validated()['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        return new AiProviderResource($provider->refresh());
    }

    /**
     * Test a stored personal provider connection.
     */
    public function test(ManageUserAiProviderRequest $request, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {

        return response()->json($tester->testProvider($provider));
    }

    /**
     * Test an unsaved personal provider configuration.
     */
    public function testConfig(TestUserAiProviderConfigurationRequest $request, AiProviderTester $tester): JsonResponse
    {
        $configuration = $request->configuration();

        return response()->json($tester->testConfig($configuration));
    }
}
