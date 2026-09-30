<?php

use App\Http\Controllers\Api\V1\AdminAiProviderController;
use App\Http\Controllers\Api\V1\AdminAiUsageController;
use App\Http\Controllers\Api\V1\AdminGoogleApprovalController;
use App\Http\Controllers\Api\V1\AdminProductImageController;
use App\Http\Controllers\Api\V1\AdminRoleController;
use App\Http\Controllers\Api\V1\AdminTranslationController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AiProviderController;
use App\Http\Controllers\Api\V1\AssistantConversationController;
use App\Http\Controllers\Api\V1\AssistantMessageController;
use App\Http\Controllers\Api\V1\AssistantProposalController;
use App\Http\Controllers\Api\V1\AssistantRunController;
use App\Http\Controllers\Api\V1\Auth\GoogleAuthController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PublicAccountController;
use App\Http\Controllers\Api\V1\Auth\RefreshController;
use App\Http\Controllers\Api\V1\CatalogController as ApiCatalogController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ContactSourceController;
use App\Http\Controllers\Api\V1\EconomicDocumentController;
use App\Http\Controllers\Api\V1\EconomicImportController;
use App\Http\Controllers\Api\V1\EconomicTransactionController;
use App\Http\Controllers\Api\V1\EconomyOverviewController;
use App\Http\Controllers\Api\V1\ForkRecipeController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HouseholdConfigurationController;
use App\Http\Controllers\Api\V1\HouseholdController;
use App\Http\Controllers\Api\V1\HouseholdDashboardController;
use App\Http\Controllers\Api\V1\HouseholdInvitationController;
use App\Http\Controllers\Api\V1\HouseholdRecipeController;
use App\Http\Controllers\Api\V1\IconController;
use App\Http\Controllers\Api\V1\InviteLinkController;
use App\Http\Controllers\Api\V1\JobMonitoringController;
use App\Http\Controllers\Api\V1\ListItemController;
use App\Http\Controllers\Api\V1\MeAccountController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MeGoogleIdentityController;
use App\Http\Controllers\Api\V1\PersonalProductController;
use App\Http\Controllers\Api\V1\PersonalShoppingListController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductSearchController;
use App\Http\Controllers\Api\V1\RecipeAiController;
use App\Http\Controllers\Api\V1\RecipeCollectionController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\RecipeExportController;
use App\Http\Controllers\Api\V1\RecipeImageController;
use App\Http\Controllers\Api\V1\RecipeImportController;
use App\Http\Controllers\Api\V1\RecipeShoppingListController;
use App\Http\Controllers\Api\V1\ShoppingListController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\UserAiProviderController;
use Illuminate\Support\Facades\Route;

// === Enlaces de invitación públicos (registro vía enlace) ===
Route::prefix('v1/invite-links')->name('api.v1.invite-links.')->group(function () {
    Route::get('{token}', [InviteLinkController::class, 'show'])->name('show');
    Route::post('{token}/register', [InviteLinkController::class, 'store'])->middleware('throttle:10,1')->name('store');
});

Route::prefix('v1/contact-sources')->name('api.v1.contact-sources.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('/', [ContactSourceController::class, 'index'])->middleware('api.permission:contacts.sources.view')->name('index');
    Route::post('/', [ContactSourceController::class, 'store'])->middleware('api.permission:contacts.sources.create')->name('store');
    Route::get('{source}', [ContactSourceController::class, 'show'])->middleware('api.permission:contacts.sources.view')->name('show');
    Route::put('{source}', [ContactSourceController::class, 'update'])->middleware('api.permission:contacts.sources.update')->name('update');
    Route::delete('{source}', [ContactSourceController::class, 'destroy'])->middleware('api.permission:contacts.sources.delete')->name('destroy');
    Route::post('{source}/test', [ContactSourceController::class, 'test'])->middleware('api.permission:contacts.sources.sync')->name('test');
    Route::post('{source}/discover', [ContactSourceController::class, 'discover'])->middleware('api.permission:contacts.sources.sync')->name('discover');
    Route::get('{source}/collections', [ContactSourceController::class, 'collections'])->middleware('api.permission:contacts.sources.view')->name('collections');
    Route::post('{source}/sync', [ContactSourceController::class, 'sync'])->middleware('api.permission:contacts.sources.sync')->name('sync');
});
Route::prefix('v1/contact-collections')->name('api.v1.contact-collections.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::patch('{collection}', [ContactSourceController::class, 'updateCollection'])->middleware('api.permission:contacts.sources.update')->name('update');
    Route::post('{collection}/sync', [ContactSourceController::class, 'syncCollection'])->middleware('api.permission:contacts.sources.sync')->name('sync');
});

Route::prefix('v1/contacts')->name('api.v1.contacts.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('/', [ContactController::class, 'index'])->middleware('api.permission:contacts.view')->name('index');
    Route::post('/', [ContactController::class, 'store'])->middleware('api.permission:contacts.create')->name('store');
    Route::get('{contact}', [ContactController::class, 'show'])->middleware('api.permission:contacts.view')->name('show');
    Route::put('{contact}', [ContactController::class, 'update'])->middleware('api.permission:contacts.update')->name('update');
    Route::patch('{contact}', [ContactController::class, 'update'])->middleware('api.permission:contacts.update')->name('patch');
    Route::delete('{contact}', [ContactController::class, 'destroy'])->middleware('api.permission:contacts.delete')->name('destroy');
});

// Auth routes (no auth required for login)
Route::prefix('v1/auth')->as('api.v1.auth.')->group(function () {
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('google', [GoogleAuthController::class, 'login'])->middleware('throttle:login')->name('google.login');
    Route::post('google/otp/setup', [GoogleAuthController::class, 'setup'])->middleware(['auth:sanctum', 'throttle:otp'])->name('google.otp.setup');
    Route::post('google/otp/confirm', [GoogleAuthController::class, 'confirm'])->middleware(['auth:sanctum', 'throttle:otp'])->name('google.otp.confirm');
    Route::post('register', [PublicAccountController::class, 'register'])->middleware('throttle:recovery')->name('register');
    Route::post('password/forgot', [PublicAccountController::class, 'forgotPassword'])->middleware('throttle:recovery')->name('password.forgot');
    Route::post('password/reset', [PublicAccountController::class, 'resetPassword'])->middleware('throttle:recovery')->name('password.reset');
    Route::post('otp/forgot', [PublicAccountController::class, 'forgotOtp'])->middleware('throttle:recovery')->name('otp.forgot');
    Route::post('otp/reset/setup', [PublicAccountController::class, 'setupOtpReset'])->middleware('throttle:recovery')->name('otp.reset.setup');
    Route::post('otp/reset/confirm', [PublicAccountController::class, 'confirmOtpReset'])->middleware('throttle:recovery')->name('otp.reset.confirm');
    Route::post('email/verification/confirm', [PublicAccountController::class, 'confirmEmail'])->middleware('throttle:recovery')->name('email-verification.confirm');
    Route::post('set-password', [PublicAccountController::class, 'setPassword'])->middleware('throttle:recovery')->name('password.set');
    Route::post('login/otp', [LoginController::class, 'loginOtp'])
        ->middleware(['auth:sanctum', 'locale', 'throttle:otp'])
        ->name('login.otp');
    Route::post('login/recovery-code', [PublicAccountController::class, 'recoveryCodeLogin'])
        ->middleware(['auth:sanctum', 'locale', 'throttle:otp'])
        ->name('login.recovery-code');
    Route::post('logout', [LogoutController::class, 'destroy'])
        ->middleware(['auth:sanctum', 'locale'])
        ->name('logout');

    Route::post('refresh', [RefreshController::class, 'refresh'])
        ->middleware(['auth:sanctum', 'locale', 'throttle:otp'])
        ->name('refresh');

    Route::post('login/otp/resend', [RefreshController::class, 'resendOtp'])
        ->middleware(['auth:sanctum', 'locale', 'throttle:otp'])
        ->name('login.otp.resend');
});

// Health check (no auth required — clients verify connectivity before sending credentials)
Route::prefix('v1')->as('api.v1.')->middleware('locale')->group(function () {
    Route::get('health', [HealthController::class, 'show'])->name('health');
});

// Read-only endpoints (auth required, no route permissions)
Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    // Perfil propio: roles y permisos del usuario autenticado (sin gate de permiso de ruta)
    Route::get('me', [MeController::class, 'show'])->name('me');
    Route::post('me/google', [MeGoogleIdentityController::class, 'store'])->name('me.google.store');
    Route::delete('me/google', [MeGoogleIdentityController::class, 'destroy'])->name('me.google.destroy');
    Route::patch('me/profile', [MeAccountController::class, 'updateProfile'])->name('me.profile.update');
    Route::put('me/password', [MeAccountController::class, 'updatePassword'])->name('me.password.update');
    Route::delete('me', [MeAccountController::class, 'destroy'])->name('me.destroy');
    Route::post('me/email-verification', [MeAccountController::class, 'sendVerification'])->middleware('throttle:recovery')->name('me.email-verification.store');
    Route::post('me/security-confirmations', [MeAccountController::class, 'confirmSecurity'])->middleware('throttle:recovery')->name('me.security-confirmations.store');
    Route::get('me/two-factor', [TwoFactorController::class, 'show'])->name('me.two-factor.show');
    Route::post('me/two-factor/setup', [TwoFactorController::class, 'setup'])->middleware('throttle:otp')->name('me.two-factor.setup');
    Route::post('me/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:otp')->name('me.two-factor.confirm');
    Route::post('me/two-factor/reset', [TwoFactorController::class, 'reset'])->middleware('throttle:otp')->name('me.two-factor.reset');
    Route::post('me/two-factor/reset/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:otp')->name('me.two-factor.reset.confirm');
    Route::get('me/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('me.two-factor.recovery-codes.index');
    Route::post('me/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:otp')->name('me.two-factor.recovery-codes.store');
    Route::delete('me/two-factor', [TwoFactorController::class, 'destroy'])->name('me.two-factor.destroy');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('icons', [IconController::class, 'index'])->name('icons.index');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');

    // Product search endpoints for mobile app
    Route::get('products/search', [ProductSearchController::class, 'search'])->name('products.search');
    Route::get('products/recent', [ProductSearchController::class, 'recent'])->name('products.recent');

});

Route::prefix('v1/assistant')->as('api.v1.assistant.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('conversations', [AssistantConversationController::class, 'index'])->name('conversations.index');
    Route::post('conversations', [AssistantConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [AssistantConversationController::class, 'show'])->name('conversations.show');
    Route::delete('conversations/{conversation}', [AssistantConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::get('conversations/{conversation}/messages', [AssistantMessageController::class, 'index'])->name('messages.index');
    Route::post('conversations/{conversation}/messages', [AssistantMessageController::class, 'store'])->middleware('idempotency')->name('messages.store');
    Route::get('runs/{run}', [AssistantRunController::class, 'show'])->name('runs.show');
    Route::post('runs/{run}/cancel', [AssistantRunController::class, 'cancel'])->name('runs.cancel');
    Route::post('conversations/{conversation}/runs/{run}/recipe', [AssistantRunController::class, 'storeRecipe'])->middleware('idempotency')->name('runs.recipe.store');
    Route::get('proposals', [AssistantProposalController::class, 'index'])->name('proposals.index');
    Route::get('proposals/{proposal}', [AssistantProposalController::class, 'show'])->name('proposals.show');
    Route::post('proposals/{proposal}/accept', [AssistantProposalController::class, 'accept'])->middleware('idempotency')->name('proposals.accept');
    Route::post('proposals/{proposal}/reject', [AssistantProposalController::class, 'reject'])->middleware('idempotency')->name('proposals.reject');
});

Route::prefix('v1/admin')->as('api.v1.admin.')->middleware(['auth:sanctum', 'locale', 'api.permission'])->group(function () {
    Route::apiResource('users', AdminUserController::class)->except('update');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/approve', [AdminGoogleApprovalController::class, 'approve'])->name('users.approve');
    Route::post('users/{user}/reject', [AdminGoogleApprovalController::class, 'reject'])->name('users.reject');
    Route::apiResource('roles', AdminRoleController::class)->except('update');
    Route::patch('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
    Route::get('permissions', [AdminRoleController::class, 'permissions'])->name('permissions.index');

    Route::get('ai-providers', [AdminAiProviderController::class, 'index'])->name('ai-providers.index');
    Route::post('ai-providers', [AdminAiProviderController::class, 'store'])->name('ai-providers.store');
    Route::post('ai-providers/test-config', [AdminAiProviderController::class, 'testConfig'])->name('ai-providers.test-config');
    Route::get('ai-providers/{provider}', [AdminAiProviderController::class, 'show'])->name('ai-providers.show');
    Route::patch('ai-providers/{provider}', [AdminAiProviderController::class, 'update'])->name('ai-providers.update');
    Route::delete('ai-providers/{provider}', [AdminAiProviderController::class, 'destroy'])->name('ai-providers.destroy');
    Route::post('ai-providers/{provider}/test', [AdminAiProviderController::class, 'test'])->name('ai-providers.test');
    Route::post('ai-providers/{provider}/default', [AdminAiProviderController::class, 'markDefault'])->name('ai-providers.default');
    Route::get('ai-modules', [AdminAiProviderController::class, 'modules'])->name('ai-modules.index');
    Route::get('ai-prompts', [AdminAiProviderController::class, 'prompts'])->name('ai-prompts.index');
    Route::put('ai-prompts/{module}', [AdminAiProviderController::class, 'updatePrompt'])->name('ai-prompts.update');
    Route::get('ai-usage', [AdminAiUsageController::class, 'index'])->name('ai-usage.index');

    // Catálogo models.dev
    Route::get('catalog/providers', [ApiCatalogController::class, 'searchProviders'])->name('catalog.providers');
    Route::get('catalog/models', [ApiCatalogController::class, 'searchModels'])->name('catalog.models');
    Route::post('catalog/prefill', [ApiCatalogController::class, 'prefill'])->name('catalog.prefill');

    Route::get('translations', [AdminTranslationController::class, 'index'])->name('translations.index');
    Route::post('translations', [AdminTranslationController::class, 'store'])->name('translations.store');
    Route::post('translations/publish', [AdminTranslationController::class, 'publish'])->name('translations.publish');
    Route::post('translations/generate', [AdminTranslationController::class, 'generate'])->middleware('idempotency:optional')->name('translations.generate');
    Route::patch('translations/{translation}', [AdminTranslationController::class, 'update'])->name('translations.update');
    Route::delete('translations/{translation}', [AdminTranslationController::class, 'destroy'])->name('translations.destroy');

    Route::get('products/pending', [AdminProductImageController::class, 'pending'])->name('products.pending.index');
    Route::post('products/{product}/generate-images', [AdminProductImageController::class, 'generate'])->middleware('idempotency')->name('products.images.generate');
    Route::get('product-image-runs/{run}', [AdminProductImageController::class, 'showRun'])->name('product-image-runs.show');
    Route::post('images/{image}/approve', [AdminProductImageController::class, 'approve'])->middleware('idempotency')->name('images.approve');
});

// === Proveedores IA personales ===
Route::prefix('v1/me/ai-providers')->as('api.v1.me.ai-providers.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('/', [UserAiProviderController::class, 'index'])->name('index');
    Route::post('/', [UserAiProviderController::class, 'store'])->name('store');
    Route::post('/test-config', [UserAiProviderController::class, 'testConfig'])->name('test-config');
    Route::put('/{provider}', [UserAiProviderController::class, 'update'])->name('update');
    Route::delete('/{provider}', [UserAiProviderController::class, 'destroy'])->name('destroy');
    Route::post('/{provider}/test', [UserAiProviderController::class, 'test'])->name('test');
    Route::post('/{provider}/default', [UserAiProviderController::class, 'markDefault'])->name('default');
});

Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum', 'locale'])->group(function () {
    Route::get('households/invitations', [HouseholdInvitationController::class, 'index'])->name('households.invitations.index');
    Route::get('households/configuration', [HouseholdConfigurationController::class, 'show'])->name('households.configuration.show');
    Route::post('households/accept', [HouseholdController::class, 'acceptInvitation'])->name('households.accept');
    Route::post('households/invitations/{invitation}/accept', [HouseholdController::class, 'acceptFromList'])->name('households.invitations.accept');
    Route::delete('households/invitations/{invitation}', [HouseholdController::class, 'cancelInvitation'])->name('households.invitations.cancel');
});

// === Cookbook personal (fuera de household context) ===
Route::prefix('v1/recipes')->as('api.v1.recipes.')->middleware(['auth:sanctum', 'locale', 'api.permission'])->group(function () {
    Route::get('/', [RecipeController::class, 'index'])->name('index');
    Route::post('/', [RecipeController::class, 'store'])->middleware('idempotency:optional')->name('store');

    // Importación (antes de las rutas con {recipe})
    Route::post('import/json-ld', [RecipeImportController::class, 'previewJsonLd'])->middleware('idempotency:optional')->name('import.json-ld');
    Route::post('import/cooklang', [RecipeImportController::class, 'previewCooklang'])->middleware('idempotency:optional')->name('import.cooklang');
    Route::post('import/review', [RecipeImportController::class, 'review'])->middleware('idempotency:optional')->name('import.review');
    Route::post('ai-generate', [RecipeAiController::class, 'generate'])->middleware('idempotency:optional')->name('ai-generate');

    Route::get('collections', [RecipeCollectionController::class, 'index'])->name('collections.index');
    Route::post('collections', [RecipeCollectionController::class, 'store'])->name('collections.store');
    Route::put('collections/{recipeCollection}', [RecipeCollectionController::class, 'update'])->name('collections.update');
    Route::delete('collections/{recipeCollection}', [RecipeCollectionController::class, 'destroy'])->name('collections.destroy');

    Route::get('{recipe}', [RecipeController::class, 'show'])->name('show');
    Route::put('{recipe}', [RecipeController::class, 'update'])->name('update');
    Route::delete('{recipe}', [RecipeController::class, 'destroy'])->name('destroy');
    Route::post('{recipe}/fork', [ForkRecipeController::class, 'store'])->name('fork');
    Route::get('{recipe}/image', [RecipeImageController::class, 'show'])->name('image');
    Route::post('{recipe}/image', [RecipeImageController::class, 'store'])->name('image.store');
    Route::delete('{recipe}/image', [RecipeImageController::class, 'destroy'])->name('image.destroy');
    Route::get('{recipe}/steps/{step}/image', [RecipeImageController::class, 'show'])->name('steps.image');
    Route::post('{recipe}/steps/{step}/image', [RecipeImageController::class, 'storeStep'])->name('steps.image.store');
    Route::delete('{recipe}/steps/{step}/image', [RecipeImageController::class, 'destroyStep'])->name('steps.image.destroy');
    Route::get('{recipe}/export/cooklang', [RecipeExportController::class, 'cooklang'])->name('export.cooklang');
});

Route::prefix('v1')->as('api.v1.')->middleware(['auth:sanctum', 'locale', 'api.permission'])->group(function () {
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::post('products/quick-create', [ProductSearchController::class, 'quickCreate'])->name('products.quick-create');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    // Productos personales
    Route::get('personal-products', [PersonalProductController::class, 'index'])->name('personal-products.index');
    Route::post('personal-products', [PersonalProductController::class, 'store'])->name('personal-products.store');

    // Listas privadas del usuario
    Route::apiResource('lists', PersonalShoppingListController::class)->except('create', 'edit');
    Route::post('lists/{list}/items/quick-create', [ListItemController::class, 'quickCreate'])->middleware('idempotency')->name('lists.items.quick-create');
    Route::post('lists/{list}/items', [ListItemController::class, 'store'])->name('lists.items.store');
    Route::put('lists/{list}/items/{item}', [ListItemController::class, 'update'])->name('lists.items.update');
    Route::delete('lists/{list}/items/{item}', [ListItemController::class, 'destroy'])->name('lists.items.destroy');
    Route::get('lists/{list}/items/{item}/image', [ListItemController::class, 'image'])->name('lists.items.image');

    // === Households ===
    Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
    Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::put('households/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::put('households/{household}/settings', [HouseholdController::class, 'update'])->name('households.settings.update');
    Route::delete('households/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
    Route::post('households/{household}/invite', [HouseholdController::class, 'invite'])->name('households.invite');
    Route::delete('households/{household}/members/{member}', [HouseholdController::class, 'removeMember'])->name('households.remove');
    Route::post('households/{household}/members/{member}/link-contact', [HouseholdController::class, 'linkMemberContact'])->name('households.members.link-contact');
    Route::post('households/{household}/invite-links', [HouseholdController::class, 'storeInviteLink'])->name('households.invite-links.store');
    Route::delete('households/{household}/invite-links/{link}', [HouseholdController::class, 'destroyInviteLink'])->name('households.invite-links.destroy');
    Route::post('households/switch/{household}', [HouseholdController::class, 'switchActive'])->name('households.switch');
    Route::get('households/{household}/dashboard', [HouseholdDashboardController::class, 'show'])->name('households.dashboard');

    // === Recursos anidadas bajo household ===
    Route::prefix('households/{household}')->as('households.')->group(function () {

        // Household image
        Route::get('image', [HouseholdController::class, 'image'])->name('image');
        Route::get('stores', [StoreController::class, 'index'])->name('stores.index');

        // Lists (requires shopping_lists module enabled)
        Route::middleware('module:shopping_lists')->group(function () {
            Route::get('lists', [ShoppingListController::class, 'index'])->name('lists.index');
            Route::post('lists', [ShoppingListController::class, 'store'])->name('lists.store');
            Route::get('lists/{list}', [ShoppingListController::class, 'show'])->scopeBindings()->name('lists.show');
            Route::put('lists/{list}', [ShoppingListController::class, 'update'])->scopeBindings()->name('lists.update');
            Route::delete('lists/{list}', [ShoppingListController::class, 'destroy'])->scopeBindings()->name('lists.destroy');

            // List Items
            Route::post('lists/{list}/items/quick-create', [ListItemController::class, 'quickCreate'])->middleware('idempotency')->scopeBindings()->name('lists.items.quick-create');
            Route::post('lists/{list}/items', [ListItemController::class, 'store'])->name('lists.items.store');
            Route::put('lists/{list}/items/{item}', [ListItemController::class, 'update'])->name('lists.items.update');
            Route::delete('lists/{list}/items/{item}', [ListItemController::class, 'destroy'])->name('lists.items.destroy');
            Route::get('lists/{list}/items/{item}/image', [ListItemController::class, 'image'])->name('lists.items.image');
        });

        // Household shared recipes (requires recipes module enabled)
        Route::middleware('module:recipes')->group(function () {
            Route::get('recipes', [HouseholdRecipeController::class, 'index'])->name('recipes.index');
            Route::post('recipes/{recipe}/share', [HouseholdRecipeController::class, 'share'])->name('recipes.share');
            Route::delete('recipes/{recipe}/share', [HouseholdRecipeController::class, 'unshare'])->name('recipes.unshare');
            Route::post('recipes/{recipe}/add-to-list/{list}', [RecipeShoppingListController::class, 'store'])->name('recipes.add-to-list');
        });

        // AI Providers
        Route::prefix('ai-providers')->as('ai-providers.')->group(function () {
            Route::get('/', [AiProviderController::class, 'index'])->name('index');
            Route::post('/', [AiProviderController::class, 'store'])->name('store');
            Route::post('/test-config', [AiProviderController::class, 'testConfig'])->name('test-config');
            Route::put('/module-config', [AiProviderController::class, 'updateModuleConfig'])->name('module-config');
            Route::get('/{provider}', [AiProviderController::class, 'show'])->name('show');
            Route::put('/{provider}', [AiProviderController::class, 'update'])->name('update');
            Route::delete('/{provider}', [AiProviderController::class, 'destroy'])->name('destroy');
            Route::post('/{provider}/test', [AiProviderController::class, 'test'])->name('test');
            Route::post('/{provider}/default', [AiProviderController::class, 'markDefault'])->name('default');
        });
    });
});

// === Economía: cuenta privada del usuario (fuera del contexto household) ===
// Cada endpoint lleva un nombre de ruta ÚNICO cuyo route_name (sin prefijo
// api.v1.) coincide con un Permission del seeder, como exige api.permission.
Route::prefix('v1/economy/me')->as('api.v1.economy.me.')->middleware(['auth:sanctum', 'locale', 'api.permission'])->group(function () {
    Route::get('overview', [EconomyOverviewController::class, 'personal'])->name('overview');
    Route::get('transactions', [EconomicTransactionController::class, 'personalIndex'])->name('transactions.index');
    Route::get('totals', [EconomicTransactionController::class, 'personalTotals'])->name('totals');
    Route::post('transactions', [EconomicTransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions/{transaction}', [EconomicTransactionController::class, 'show'])->name('transactions.show');
    Route::put('transactions/{transaction}', [EconomicTransactionController::class, 'update'])->name('transactions.update');
    Route::patch('transactions/{transaction}', [EconomicTransactionController::class, 'update'])->name('transactions.patch');
    Route::delete('transactions/{transaction}', [EconomicTransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::post('documents', [EconomicDocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [EconomicDocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/file', [EconomicDocumentController::class, 'file'])->name('documents.file');
    Route::delete('documents/{document}', [EconomicDocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('imports', [EconomicImportController::class, 'store'])->middleware('idempotency:optional')->name('imports.store');
    Route::get('imports/{import}', [EconomicImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{import}/confirm', [EconomicImportController::class, 'confirm'])->middleware('idempotency')->name('imports.confirm');
    Route::post('imports/{import}/retry', [EconomicImportController::class, 'retry'])->middleware('idempotency')->name('imports.retry');
    Route::delete('imports/{import}', [EconomicImportController::class, 'discard'])->name('imports.destroy');
});

// === Economía de casa ===
Route::prefix('v1/households/{household}/economy')->as('api.v1.households.economy.')->middleware(['auth:sanctum', 'locale', 'api.permission', 'module:economy'])->group(function () {
    Route::get('overview', [EconomyOverviewController::class, 'household'])->name('overview');
    Route::get('transactions', [EconomicTransactionController::class, 'index'])->name('transactions.index');
    Route::get('totals', [EconomicTransactionController::class, 'totals'])->name('totals');
    Route::post('transactions', [EconomicTransactionController::class, 'store'])->name('transactions.store');
    Route::get('transactions/{transaction}', [EconomicTransactionController::class, 'show'])->name('transactions.show');
    Route::put('transactions/{transaction}', [EconomicTransactionController::class, 'update'])->name('transactions.update');
    Route::patch('transactions/{transaction}', [EconomicTransactionController::class, 'update'])->name('transactions.patch');
    Route::delete('transactions/{transaction}', [EconomicTransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::post('documents', [EconomicDocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}', [EconomicDocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/file', [EconomicDocumentController::class, 'file'])->name('documents.file');
    Route::delete('documents/{document}', [EconomicDocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('imports', [EconomicImportController::class, 'store'])->middleware('idempotency:optional')->name('imports.store');
    Route::get('imports/{import}', [EconomicImportController::class, 'show'])->name('imports.show');
    Route::post('imports/{import}/confirm', [EconomicImportController::class, 'confirm'])->middleware('idempotency')->name('imports.confirm');
    Route::post('imports/{import}/retry', [EconomicImportController::class, 'retry'])->middleware('idempotency')->name('imports.retry');
    Route::delete('imports/{import}', [EconomicImportController::class, 'discard'])->name('imports.destroy');
});

// === Job Monitoring (workers de cola) ===
// Los nombres api.v1.jobs-monitor.* resuelven los permisos web jobs-monitor.*
// (directamente o vía los alias de ApiPermissionsMiddleware).
Route::prefix('v1/jobs-monitor')->as('api.v1.jobs-monitor.')->middleware(['auth:sanctum', 'locale', 'api.permission'])->group(function () {
    Route::get('/', [JobMonitoringController::class, 'index'])->name('index');
    Route::get('jobs', [JobMonitoringController::class, 'index'])->name('jobs');
    Route::get('jobs/{id}', [JobMonitoringController::class, 'show'])->name('show');
    Route::post('jobs/{id}/retry', [JobMonitoringController::class, 'retry'])->name('retry');
    Route::get('statistics', [JobMonitoringController::class, 'statistics'])->name('statistics');
    Route::get('queue-depth', [JobMonitoringController::class, 'queueDepth'])->name('queue-depth');
    Route::get('failed', [JobMonitoringController::class, 'failedJobs'])->name('failed');
    Route::get('tags/{tag}', [JobMonitoringController::class, 'jobsByTag'])->name('jobs-by-tag');
});
