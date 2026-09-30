<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\CreateUserAiProvider;
use App\Actions\Households\MarkAiProviderDefault;
use App\Actions\Households\UpdateAiProvider;
use App\Data\Households\CreateAiProviderData;
use App\Data\Households\UpdateAiProviderData;
use App\Enums\AiProviderModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserAiProviderRequest;
use App\Http\Requests\Settings\UpdateUserAiProviderRequest;
use App\Http\Resources\Households\AiProviderResource;
use App\Models\User;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
        $provider = $this->ownedProvider($this->authenticatedUser($request), $provider);
        $data = UpdateAiProviderData::fromArray($request->validated());

        return new AiProviderResource($action->execute($provider, $data));
    }

    /**
     * Delete a personal provider.
     */
    public function destroy(Request $request, AiProvider $provider): Response
    {
        $this->ownedProvider($this->authenticatedUser($request), $provider)->delete();

        return response()->noContent();
    }

    /**
     * Mark a personal provider as default for its module.
     */
    public function markDefault(
        Request $request,
        AiProvider $provider,
        MarkAiProviderDefault $action,
    ): AiProviderResource {
        $provider = $this->ownedProvider($this->authenticatedUser($request), $provider);
        $module = $request->validate(['module' => ['sometimes', 'string', Rule::in(AiProviderModule::cases())]])['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        return new AiProviderResource($provider->refresh());
    }

    /**
     * Test a stored personal provider connection.
     */
    public function test(Request $request, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {
        $provider = $this->ownedProvider($this->authenticatedUser($request), $provider);

        return response()->json($tester->testProvider($provider));
    }

    /**
     * Test an unsaved personal provider configuration.
     */
    public function testConfig(Request $request, AiProviderTester $tester): JsonResponse
    {
        $configuration = $request->validate([
            'type' => ['required', 'string', Rule::in(AiDriver::cases())],
            'base_url' => ['required', 'url', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($tester->testConfig($configuration));
    }

    /**
     * Resolve a route-bound provider within the authenticated user's scope.
     */
    private function ownedProvider(User $user, AiProvider $provider): AiProvider
    {
        abort_unless($provider->isUser() && $provider->user_id === $user->id, 404);

        return $provider;
    }
}
