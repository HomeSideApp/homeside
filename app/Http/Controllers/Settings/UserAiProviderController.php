<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

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
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles AI provider settings owned by the authenticated user.
 */
final class UserAiProviderController extends Controller
{
    /**
     * Display the authenticated user's personal AI providers.
     *
     * @return Response The Inertia settings page response.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $providers = AiProvider::query()
            ->whereNull('household_id')
            ->where('user_id', $user->id)
            ->oldest()
            ->get()
            ->map($this->serializeProvider(...));

        return Inertia::render('settings/AiProviders', [
            'providers' => $providers,
        ]);
    }

    /**
     * Store a new AI provider for the authenticated user.
     *
     * @param  StoreUserAiProviderRequest  $request  The validated personal provider request.
     * @param  CreateUserAiProvider  $action  The action that creates the personal provider.
     * @return RedirectResponse A redirect back to the provider settings page.
     */
    public function store(StoreUserAiProviderRequest $request, CreateUserAiProvider $action): RedirectResponse
    {
        $action->execute(
            CreateAiProviderData::fromArray($request->validated()),
            $this->authenticatedUser($request),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.user_ai_provider_created'),
        ]);

        return back();
    }

    /**
     * Update an AI provider owned by the authenticated user.
     *
     * @param  UpdateUserAiProviderRequest  $request  The validated provider update request.
     * @param  AiProvider  $provider  The route-bound provider to update.
     * @param  UpdateAiProvider  $action  The action that applies provider changes.
     * @return RedirectResponse A redirect back to the provider settings page.
     */
    public function update(
        UpdateUserAiProviderRequest $request,
        AiProvider $provider,
        UpdateAiProvider $action,
    ): RedirectResponse {
        $data = UpdateAiProviderData::fromArray($request->validated());
        $action->execute($provider, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.user_ai_provider_updated'),
        ]);

        return back();
    }

    /**
     * Delete an AI provider owned by the authenticated user.
     *
     * @param  AiProvider  $provider  The route-bound provider to delete.
     * @return RedirectResponse A redirect back to the provider settings page.
     */
    public function destroy(ManageUserAiProviderRequest $request, AiProvider $provider): RedirectResponse
    {
        $provider->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.user_ai_provider_deleted'),
        ]);

        return back();
    }

    /**
     * Mark a personal AI provider as default for its module.
     *
     * @param  AiProvider  $provider  The route-bound provider to mark as default.
     * @param  MarkAiProviderDefault  $action  The action that changes the default provider.
     * @return RedirectResponse A redirect back to the provider settings page.
     */
    public function markDefault(
        DefaultUserAiProviderRequest $request,
        AiProvider $provider,
        MarkAiProviderDefault $action,
    ): RedirectResponse {
        $module = $request->validated()['module'] ?? $provider->module;
        $provider->markAsDefaultForModule($module);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.toast.ai_provider_marked_default'),
        ]);

        return back();
    }

    /**
     * Test a stored personal AI provider connection.
     *
     * @param  AiProvider  $provider  The route-bound provider to test.
     * @param  AiProviderTester  $tester  The service that probes the provider.
     * @return JsonResponse The provider connection test result.
     */
    public function test(ManageUserAiProviderRequest $request, AiProvider $provider, AiProviderTester $tester): JsonResponse
    {

        return response()->json($tester->testProvider($provider));
    }

    /**
     * Test unsaved personal AI provider credentials.
     *
     * @param  AiProviderTester  $tester  The service that probes the supplied configuration.
     * @return JsonResponse The provider connection test result.
     */
    public function testConfig(TestUserAiProviderConfigurationRequest $request, AiProviderTester $tester): JsonResponse
    {
        $configuration = $request->configuration();

        return response()->json($tester->testConfig($configuration));
    }

    /**
     * Serialize an AI provider without exposing its encrypted API key.
     *
     * @param  AiProvider  $provider  The personal provider to serialize.
     * @return array{id: string, name: string, type: string, driver: string, base_url: string, model: string, module: string, module_label: string, enabled: bool, is_default: bool, configuration: array<string, mixed>|null, privacy_level: string, fallback_policy: string, family: string|null, description: string|null, attachment: bool, reasoning: bool, reasoning_options: array<string, mixed>|null, tool_call: bool, structured_output: bool, temperature: float|null, open_weights: bool, modalities_input: array<string, string>|null, modalities_output: array<string, string>|null, context_window: int|null, max_input_tokens: int|null, max_output_tokens: int|null, cost_input: float|null, cost_output: float|null, cost_cache_read: float|null, cost_cache_write: float|null} The safe frontend provider payload.
     */
    private function serializeProvider(AiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'type' => $provider->type,
            'driver' => $provider->driver ?? $provider->type,
            'base_url' => $provider->base_url,
            'model' => $provider->model,
            'module' => $provider->module,
            'modules' => $provider->assignedModules(),
            'default_modules' => $provider->defaultModules(),
            'module_label' => trans_choice('app.ai_provider_modules.'.$provider->module, 1),
            'enabled' => $provider->enabled,
            'is_default' => $provider->is_default,
            'configuration' => $provider->configuration,
            'privacy_level' => $provider->privacy_level ?? 'unknown',
            'fallback_policy' => $provider->fallback_policy ?? 'same_privacy_level',
            'family' => $provider->family,
            'description' => $provider->description,
            'attachment' => $provider->attachment ?? false,
            'reasoning' => $provider->reasoning ?? false,
            'reasoning_options' => $provider->reasoning_options,
            'tool_call' => $provider->tool_call ?? false,
            'structured_output' => $provider->structured_output ?? false,
            'temperature' => $provider->temperature,
            'open_weights' => $provider->open_weights ?? false,
            'modalities_input' => $provider->modalities_input,
            'modalities_output' => $provider->modalities_output,
            'context_window' => $provider->context_window,
            'max_input_tokens' => $provider->max_input_tokens,
            'max_output_tokens' => $provider->max_output_tokens,
            'cost_input' => $provider->cost_input,
            'cost_output' => $provider->cost_output,
            'cost_cache_read' => $provider->cost_cache_read,
            'cost_cache_write' => $provider->cost_cache_write,
        ];
    }
}
