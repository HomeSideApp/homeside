<?php

use App\Http\Controllers\Admin\AdminAiProviderController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminPersonalProductController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminTranslationController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\GoogleApprovalController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\OtpSetupController;
use App\Http\Controllers\ProductSearchController;
use App\Http\Controllers\Web\AiProviderController;
use App\Http\Controllers\Web\AiUsageController;
use App\Http\Controllers\Web\AssistantController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\ContactDuplicateController;
use App\Http\Controllers\Web\ContactLabelController;
use App\Http\Controllers\Web\ContactSourceController;
use App\Http\Controllers\Web\EconomicAccountController;
use App\Http\Controllers\Web\EconomicDocumentController;
use App\Http\Controllers\Web\EconomicImportController;
use App\Http\Controllers\Web\EconomicTransactionAttachmentController;
use App\Http\Controllers\Web\EconomicTransactionController;
use App\Http\Controllers\Web\ForkRecipeController;
use App\Http\Controllers\Web\GoogleContactSourceController;
use App\Http\Controllers\Web\HouseholdController;
use App\Http\Controllers\Web\HouseholdRecipeController;
use App\Http\Controllers\Web\ImageController;
use App\Http\Controllers\Web\JobMonitoringController;
use App\Http\Controllers\Web\ListItemController;
use App\Http\Controllers\Web\PaymentMethodController;
use App\Http\Controllers\Web\PersonalEconomyOverviewController;
use App\Http\Controllers\Web\PersonalProductController;
use App\Http\Controllers\Web\PersonalShoppingListController;
use App\Http\Controllers\Web\ProductController;
use App\Http\Controllers\Web\ProductSearchController as WebProductSearchController;
use App\Http\Controllers\Web\RecipeAiController;
use App\Http\Controllers\Web\RecipeCollectionController;
use App\Http\Controllers\Web\RecipeController;
use App\Http\Controllers\Web\RecipeExportController;
use App\Http\Controllers\Web\RecipeImportController;
use App\Http\Controllers\Web\RecipeShoppingListController;
use App\Http\Controllers\Web\ShoppingListController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    /**
     * Redirect the user to the dashboard.
     */
    return redirect()->route('dashboard');
})->name('home');

// === Enlaces de invitación públicos (registro vía enlace) ===
Route::get('invite/{token}', [InviteLinkController::class, 'show'])->name('invite-links.show');
Route::post('invite/{token}', [InviteLinkController::class, 'store'])->middleware('throttle:10,1')->name('invite-links.store');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->middleware('permission:contacts.view')->name('index');
        Route::post('/', [ContactController::class, 'store'])->middleware('permission:contacts.create')->name('store');
        Route::get('duplicates', [ContactDuplicateController::class, 'index'])->middleware('permission:contacts.view')->name('duplicates.index');
        Route::post('duplicates', [ContactDuplicateController::class, 'store'])->middleware('permission:contacts.update')->name('duplicates.store');
        Route::prefix('sources')->name('sources.')->group(function () {
            Route::get('/', [ContactSourceController::class, 'index'])->middleware('permission:contacts.sources.view')->name('index');
            Route::get('google/connect', [GoogleContactSourceController::class, 'redirect'])->middleware(['permission:contacts.sources.create', 'throttle:6,1'])->name('google.connect');
            Route::get('google/callback', [GoogleContactSourceController::class, 'callback'])->middleware('throttle:6,1')->name('google.callback');
            Route::post('google', [GoogleContactSourceController::class, 'store'])->middleware('permission:contacts.sources.create')->name('google.store');
            Route::put('google/{source}', [GoogleContactSourceController::class, 'update'])->middleware('permission:contacts.sources.update')->name('google.update');
            Route::get('google/{source}/reconnect', [GoogleContactSourceController::class, 'reconnect'])->middleware(['permission:contacts.sources.update', 'throttle:6,1'])->name('google.reconnect');
            // Draft connection probe: verifies the typed credentials without persisting anything.
            Route::post('test-connection', [ContactSourceController::class, 'testConnection'])->middleware('permission:contacts.sources.create')->name('test-connection');
            Route::post('discover-server', [ContactSourceController::class, 'discoverServer'])->middleware(['permission:contacts.sources.create', 'throttle:6,1'])->name('discover-server');
            Route::post('preview', [ContactSourceController::class, 'preview'])->middleware('throttle:6,1')->name('preview');
            Route::post('/', [ContactSourceController::class, 'store'])->middleware('permission:contacts.sources.create')->name('store');
            Route::put('{source}', [ContactSourceController::class, 'update'])->middleware('permission:contacts.sources.update')->name('update');
            Route::delete('{source}', [ContactSourceController::class, 'destroy'])->middleware('permission:contacts.sources.delete')->name('destroy');
            Route::post('{source}/test', [ContactSourceController::class, 'test'])->middleware('permission:contacts.sources.sync')->name('test');
            Route::post('{source}/discover', [ContactSourceController::class, 'discover'])->middleware('permission:contacts.sources.sync')->name('discover');
            Route::post('{source}/sync', [ContactSourceController::class, 'sync'])->middleware('permission:contacts.sources.sync')->name('sync');
        });
        Route::put('collections/{collection}', [ContactSourceController::class, 'updateCollection'])->middleware('permission:contacts.sources.update')->name('collections.update');
        Route::post('collections/{collection}/sync', [ContactSourceController::class, 'syncCollection'])->middleware('permission:contacts.sources.sync')->name('collections.sync');
        Route::post('labels', [ContactLabelController::class, 'store'])->middleware('permission:contacts.create')->name('labels.store');
        Route::delete('labels/{label}', [ContactLabelController::class, 'destroy'])->middleware('permission:contacts.create')->name('labels.destroy');
        Route::put('{contact}/labels', [ContactController::class, 'updateLabels'])->middleware('permission:contacts.update')->name('labels.update');
        Route::get('lookup', [ContactController::class, 'lookup'])->middleware('permission:contacts.view')->name('lookup');
        Route::get('{contact}/avatar', [ContactController::class, 'avatar'])->middleware('permission:contacts.view')->name('avatar');
        Route::get('{contact}/edit', [ContactController::class, 'edit'])->middleware('permission:contacts.update')->name('edit');
        Route::put('{contact}/remote-fields', [ContactController::class, 'useRemoteFields'])->middleware('permission:contacts.update')->name('remote-fields.update');
        Route::get('{contact}', [ContactController::class, 'show'])->middleware('permission:contacts.view')->name('show');
        Route::put('{contact}', [ContactController::class, 'update'])->middleware('permission:contacts.update')->name('update');
        Route::delete('{contact}', [ContactController::class, 'destroy'])->middleware('permission:contacts.delete')->name('destroy');
    });

    // === Rutas exentas del gate de hogar ===
    Route::get('households/select', [HouseholdController::class, 'select'])->name('households.select');
    Route::get('households/create', [HouseholdController::class, 'create'])->middleware('permission:households.create')->name('households.create');

    // Unified image serving
    Route::get('images/{type}/{uuid}', [ImageController::class, 'show'])->name('images.show');
    Route::post('households', [HouseholdController::class, 'store'])->middleware('permission:households.store')->name('households.store');
    Route::post('households/switch/{household}', [HouseholdController::class, 'switchActive'])->middleware('permission:households.switch')->name('households.switch');
    Route::get('households/accept/{token}', [HouseholdController::class, 'acceptInvitation'])->name('households.accept');
    Route::post('households/invitations/{invitation}/accept', [HouseholdController::class, 'acceptFromList'])->name('households.invitations.accept');
    Route::delete('households/invitations/{invitation}', [HouseholdController::class, 'cancelInvitation'])->name('households.invitations.cancel');

    // Dashboard personal o redirección al dashboard del hogar activo
    Route::get('dashboard', [HouseholdController::class, 'redirectToActive'])->name('dashboard');

    // API: Búsqueda y categorías (no necesitan household)
    Route::get('api/products/search', [ProductSearchController::class, 'search'])->name('api.products.search');
    Route::get('api/products/recent', [ProductSearchController::class, 'recent'])->name('api.products.recent');
    Route::get('api/categories', [CategoryController::class, 'index'])->name('api.categories');

    // Catálogo models.dev (búsqueda standalone — funciona desde cualquier página)
    Route::get('catalog/search-models', [CatalogController::class, 'searchModelsStandalone'])->name('catalog.search-models');

    // Admin: Usuarios (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->middleware('permission:admin.users')->name('users');
        Route::get('users/create', [AdminUserController::class, 'create'])->middleware('permission:admin.users.create')->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->middleware('permission:admin.users.store')->name('users.store');
        Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->middleware('permission:admin.users.edit')->name('users.edit');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->middleware('permission:admin.users.update')->name('users.update');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->middleware('permission:admin.users.destroy')->name('users.destroy');
        Route::post('users/{user}/approve', [GoogleApprovalController::class, 'approve'])->middleware('permission:admin.users.update')->name('users.approve');
        Route::post('users/{user}/reject', [GoogleApprovalController::class, 'reject'])->middleware('permission:admin.users.update')->name('users.reject');
    });

    // Admin: Roles (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('roles', [AdminRoleController::class, 'index'])->middleware('permission:admin.roles')->name('roles');
        Route::get('roles/create', [AdminRoleController::class, 'create'])->middleware('permission:admin.roles.create')->name('roles.create');
        Route::post('roles', [AdminRoleController::class, 'store'])->middleware('permission:admin.roles.store')->name('roles.store');
        Route::get('roles/{role}', [AdminRoleController::class, 'show'])->middleware('permission:admin.roles')->name('roles.show');
        Route::get('roles/{role}/edit', [AdminRoleController::class, 'edit'])->middleware('permission:admin.roles.edit')->name('roles.edit');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])->middleware('permission:admin.roles.update')->name('roles.update');
        Route::delete('roles/{role}', [AdminRoleController::class, 'destroy'])->middleware('permission:admin.roles.destroy')->name('roles.destroy');
    });

    // Admin: Categorías (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('categories', [AdminCategoryController::class, 'index'])->middleware('permission:admin.categories')->name('categories');
        Route::get('categories/create', [AdminCategoryController::class, 'create'])->middleware('permission:admin.categories.create')->name('categories.create');
        Route::post('categories', [AdminCategoryController::class, 'store'])->middleware('permission:admin.categories.store')->name('categories.store');
        Route::get('categories/{category}/edit', [AdminCategoryController::class, 'edit'])->middleware('permission:admin.categories.edit')->name('categories.edit');
        Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->middleware('permission:admin.categories.update')->name('categories.update');
        Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->middleware('permission:admin.categories.destroy')->name('categories.destroy');
    });

    // Admin: Traducciones (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('translations', [AdminTranslationController::class, 'index'])->middleware('permission:admin.translations')->name('translations');
        Route::post('translations', [AdminTranslationController::class, 'store'])->middleware('permission:admin.translations.store')->name('translations.store');
        Route::post('translations/publish', [AdminTranslationController::class, 'publish'])->middleware('permission:admin.translations.publish')->name('translations.publish');
        Route::post('translations/publish-bulk', [AdminTranslationController::class, 'publishBulk'])->middleware('permission:admin.translations.publish')->name('translations.publish-bulk');
        Route::post('translations/generate', [AdminTranslationController::class, 'generate'])->middleware('permission:admin.translations.generate')->name('translations.generate');
        Route::put('translations/{translation}', [AdminTranslationController::class, 'update'])->middleware('permission:admin.translations.update')->name('translations.update');
        Route::delete('translations/{translation}', [AdminTranslationController::class, 'destroy'])->middleware('permission:admin.translations.destroy')->name('translations.destroy');
    });

    // Admin: Productos (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('products', [AdminProductController::class, 'index'])->middleware('permission:admin.products')->name('products');
        Route::get('products/create', [AdminProductController::class, 'create'])->middleware('permission:admin.products.create')->name('products.create');
        Route::post('products', [AdminProductController::class, 'store'])->middleware('permission:admin.products.store')->name('products.store');
        Route::get('products/{product}/edit', [AdminProductController::class, 'edit'])->middleware('permission:admin.products.edit')->name('products.edit');
        Route::put('products/{product}', [AdminProductController::class, 'update'])->middleware('permission:admin.products.update')->name('products.update');
        Route::delete('products/{product}', [AdminProductController::class, 'destroy'])->middleware('permission:admin.products.destroy')->name('products.destroy');
        Route::get('products/pending', [AdminProductController::class, 'pending'])->middleware('permission:admin.products.pending')->name('products.pending');
        Route::post('products/{product}/generate-images', [AdminProductController::class, 'generateImages'])->middleware('permission:admin.products.generate-images')->name('products.generate-images');
        Route::post('images/{image}/approve', [AdminProductController::class, 'approveImage'])->middleware('permission:admin.images.approve')->name('images.approve');
        Route::get('personal-products', [AdminPersonalProductController::class, 'index'])->name('personal-products');
        Route::get('personal-products/{product}', [AdminPersonalProductController::class, 'show'])->name('personal-products.show');
        Route::post('personal-products/{product}/promote', [AdminPersonalProductController::class, 'promote'])->name('personal-products.promote');
        Route::post('personal-products/generate-icon', [AdminPersonalProductController::class, 'generateIcon'])->name('personal-products.generate-icon');
    });

    // Admin: IA Global (no necesitan household)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('ai-providers', [AdminAiProviderController::class, 'index'])->middleware('permission:admin.ai-providers')->name('ai-providers');
        Route::post('ai-providers', [AdminAiProviderController::class, 'store'])->middleware('permission:admin.ai-providers.store')->name('ai-providers.store');
        Route::post('ai-providers/test-config', [AdminAiProviderController::class, 'testConfig'])->middleware('permission:admin.ai-providers.test-config')->name('ai-providers.test-config');
        Route::put('ai-providers/{provider}', [AdminAiProviderController::class, 'update'])->middleware('permission:admin.ai-providers.update')->name('ai-providers.update');
        Route::delete('ai-providers/{provider}', [AdminAiProviderController::class, 'destroy'])->middleware('permission:admin.ai-providers.destroy')->name('ai-providers.destroy');
        Route::post('ai-providers/{provider}/test', [AdminAiProviderController::class, 'test'])->middleware('permission:admin.ai-providers.test')->name('ai-providers.test');
        Route::post('ai-providers/{provider}/default', [AdminAiProviderController::class, 'markDefault'])->middleware('permission:admin.ai-providers.update')->name('ai-providers.default');
        Route::put('ai-settings/global-prompt', [AdminAiProviderController::class, 'updateGlobalPrompt'])->middleware('permission:admin.ai-settings.update')->name('ai-settings.global-prompt');
        Route::put('ai-settings/module-prompt', [AdminAiProviderController::class, 'updateModulePrompt'])->middleware('permission:admin.ai-settings.update')->name('ai-settings.module-prompt');
        Route::get('ai-usage', [AiUsageController::class, 'index'])->middleware('permission:admin.ai-providers')->name('ai-usage');

        // Catálogo models.dev (búsqueda parcial via Inertia)
        Route::get('ai-providers/catalog/search', [CatalogController::class, 'searchProvidersAdmin'])->middleware('permission:admin.ai-providers')->name('ai-providers.catalog.search');
    });

    // === Cookbook personal (fuera de household context) ===
    Route::prefix('recipes')->name('recipes.')->group(function () {
        Route::get('/', [RecipeController::class, 'index'])->name('index');
        Route::get('create', [RecipeController::class, 'create'])->name('create');
        Route::post('/', [RecipeController::class, 'store'])->name('store');

        Route::get('collections', [RecipeCollectionController::class, 'index'])->name('collections.index');
        Route::post('collections', [RecipeCollectionController::class, 'store'])->name('collections.store');
        Route::put('collections/{recipeCollection}', [RecipeCollectionController::class, 'update'])->name('collections.update');
        Route::delete('collections/{recipeCollection}', [RecipeCollectionController::class, 'destroy'])->name('collections.destroy');

        // Importación (ANTES de {recipe} para evitar conflicto con wildcard)
        Route::get('import', [RecipeImportController::class, 'create'])->name('import');
        Route::post('import/json-ld', [RecipeImportController::class, 'previewJsonLd'])->name('import.json-ld');
        Route::post('import/cooklang', [RecipeImportController::class, 'previewCooklang'])->name('import.cooklang');
        Route::match(['get', 'post'], 'import/review', [RecipeImportController::class, 'review'])->name('import.review');

        // Generación con IA (ANTES de {recipe} para evitar conflicto con wildcard)
        Route::post('ai-generate', [RecipeAiController::class, 'generate'])->name('ai-generate');

        // Rutas con {recipe} (DESPUÉS de rutas fijas)
        Route::get('{recipe}', [RecipeController::class, 'show'])->name('show');
        Route::get('{recipe}/edit', [RecipeController::class, 'edit'])->name('edit');
        Route::put('{recipe}', [RecipeController::class, 'update'])->name('update');
        Route::delete('{recipe}', [RecipeController::class, 'destroy'])->name('destroy');
        Route::post('{recipe}/fork', [ForkRecipeController::class, 'store'])->name('fork');
        Route::get('{recipe}/export/cooklang', [RecipeExportController::class, 'cooklang'])->name('export.cooklang');
        Route::get('{recipe}/cook', [RecipeController::class, 'cook'])->name('cook');
    });

    // === Economía: cuenta privada del usuario (fuera del gate household/module) ===
    Route::prefix('economy/me')->name('economy.me.')->group(function () {
        Route::get('/', [PersonalEconomyOverviewController::class, 'index'])->middleware('permission:economy.me.index')->name('index');

        // Cuentas y métodos de pago (ANTES del comodín {transaction} para evitar shadowing)
        Route::get('accounts', [EconomicAccountController::class, 'index'])->middleware('permission:economy.me.accounts.index')->name('accounts.index');
        Route::get('accounts/create', [EconomicAccountController::class, 'create'])->middleware('permission:economy.me.accounts.create')->name('accounts.create');
        Route::post('accounts', [EconomicAccountController::class, 'store'])->middleware('permission:economy.me.accounts.store')->name('accounts.store');
        Route::get('accounts/{account}', [EconomicAccountController::class, 'show'])->middleware('permission:economy.me.accounts.show')->name('accounts.show');
        Route::get('accounts/{account}/edit', [EconomicAccountController::class, 'edit'])->middleware('permission:economy.me.accounts.edit')->name('accounts.edit');
        Route::put('accounts/{account}', [EconomicAccountController::class, 'update'])->middleware('permission:economy.me.accounts.update')->name('accounts.update');
        Route::post('accounts/{account}/archive', [EconomicAccountController::class, 'archive'])->middleware('permission:economy.me.accounts.archive')->name('accounts.archive');
        Route::delete('accounts/{account}', [EconomicAccountController::class, 'destroy'])->middleware('permission:economy.me.accounts.destroy')->name('accounts.destroy');

        Route::get('payment-methods', [PaymentMethodController::class, 'index'])->middleware('permission:economy.me.payment-methods.index')->name('payment-methods.index');
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->middleware('permission:economy.me.payment-methods.store')->name('payment-methods.store');
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->middleware('permission:economy.me.payment-methods.update')->name('payment-methods.update');
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->middleware('permission:economy.me.payment-methods.destroy')->name('payment-methods.destroy');

        // Ruta fija ANTES del comodín {transaction} para evitar shadowing
        Route::get('imports', [EconomicImportController::class, 'index'])->middleware('permission:economy.me.imports.index')->name('imports.index');

        Route::get('create', [EconomicTransactionController::class, 'create'])->middleware('permission:economy.me.create')->name('create');
        Route::post('/', [EconomicTransactionController::class, 'store'])->middleware('permission:economy.me.store')->name('store');
        Route::get('{transaction}', [EconomicTransactionController::class, 'show'])->middleware('permission:economy.me.show')->name('show');
        Route::post('{transaction}/attachments', [EconomicTransactionAttachmentController::class, 'store'])->middleware('permission:economy.me.update')->name('attachments.store');
        Route::delete('{transaction}/attachments/{attachment}', [EconomicTransactionAttachmentController::class, 'destroy'])->middleware('permission:economy.me.update')->name('attachments.destroy');
        Route::get('{transaction}/attachments/{attachment}/file', [EconomicTransactionAttachmentController::class, 'file'])->middleware('permission:economy.me.show')->name('attachments.file');
        Route::get('{transaction}/edit', [EconomicTransactionController::class, 'edit'])->middleware('permission:economy.me.edit')->name('edit');
        Route::put('{transaction}', [EconomicTransactionController::class, 'update'])->middleware('permission:economy.me.update')->name('update');
        Route::delete('{transaction}', [EconomicTransactionController::class, 'destroy'])->middleware('permission:economy.me.destroy')->name('destroy');

        Route::post('documents', [EconomicDocumentController::class, 'store'])->middleware('permission:economy.me.create')->name('documents.store');
        Route::delete('documents/{document}', [EconomicDocumentController::class, 'destroy'])->middleware('permission:economy.me.destroy')->name('documents.destroy');
        Route::get('documents/{document}/file', [EconomicDocumentController::class, 'file'])->middleware('permission:economy.me.show')->name('documents.file');

        Route::get('imports/create', [EconomicImportController::class, 'create'])->middleware('permission:economy.me.create')->name('imports.create');
        Route::post('imports', [EconomicImportController::class, 'store'])->middleware('permission:economy.me.imports.store')->name('imports.store');
        Route::get('imports/{import}', [EconomicImportController::class, 'show'])->middleware('permission:economy.me.show')->name('imports.show');
        Route::post('imports/{import}/confirm', [EconomicImportController::class, 'confirm'])->middleware('permission:economy.me.store')->name('imports.confirm');
        Route::post('imports/{import}/retry', [EconomicImportController::class, 'retry'])->middleware('permission:economy.me.create')->name('imports.retry');
        Route::post('imports/{import}/reprocess', [EconomicImportController::class, 'reprocess'])->middleware('permission:economy.me.create')->name('imports.reprocess');
        Route::post('imports/{import}/discard', [EconomicImportController::class, 'discard'])->middleware('permission:economy.me.destroy')->name('imports.discard');
    });

    // === Listas privadas del usuario (fuera del contexto de hogar) ===
    Route::prefix('lists')->name('lists.')->group(function () {
        Route::get('/', [PersonalShoppingListController::class, 'index'])->middleware('permission:households.lists.index')->name('index');
        Route::get('create', [PersonalShoppingListController::class, 'create'])->middleware('permission:households.lists.create')->name('create');
        Route::post('/', [PersonalShoppingListController::class, 'store'])->middleware('permission:households.lists.store')->name('store');
        Route::get('{list}', [PersonalShoppingListController::class, 'show'])->middleware('permission:households.lists.show')->name('show');
        Route::get('{list}/edit', [PersonalShoppingListController::class, 'edit'])->middleware('permission:households.lists.edit')->name('edit');
        Route::put('{list}', [PersonalShoppingListController::class, 'update'])->middleware('permission:households.lists.update')->name('update');
        Route::delete('{list}', [PersonalShoppingListController::class, 'destroy'])->middleware('permission:households.lists.destroy')->name('destroy');
        Route::post('{list}/items', [ListItemController::class, 'store'])->middleware('permission:households.lists.items.store')->name('items.store');
        Route::put('{list}/items/{item}', [ListItemController::class, 'update'])->middleware('permission:households.lists.items.update')->name('items.update');
        Route::delete('{list}/items/{item}', [ListItemController::class, 'destroy'])->middleware('permission:households.lists.items.destroy')->name('items.destroy');
        Route::get('{list}/items/{item}/image', [ListItemController::class, 'image'])->name('items.image');
        Route::get('{list}/search-products', [ProductSearchController::class, 'personalSearch'])->middleware('permission:households.lists.search-products')->name('search-products');
        Route::post('{list}/search-products/quick-create', [ProductSearchController::class, 'personalQuickCreate'])->middleware('permission:households.lists.search-products.quick-create')->name('search-products.quick-create');
    });

    // === Todas las demás rutas requieren hogar activo ===
    Route::middleware('household')->group(function () {

        // Hogares (CRUD completo)
        Route::get('households', [HouseholdController::class, 'index'])->middleware('permission:households.index')->name('households.index');
        Route::get('households/{household}', [HouseholdController::class, 'show'])->middleware('permission:households.show')->name('households.show');
        Route::delete('households/{household}', [HouseholdController::class, 'destroy'])->middleware('permission:households.destroy')->name('households.destroy');
        Route::post('households/{household}/invite', [HouseholdController::class, 'invite'])->middleware('permission:households.invite')->name('households.invite');
        Route::delete('households/{household}/members/{member}', [HouseholdController::class, 'removeMember'])->middleware('permission:households.remove')->name('households.remove');
        Route::post('households/{household}/members/{member}/link-contact', [HouseholdController::class, 'linkMemberContact'])->name('households.members.link-contact');
        Route::post('households/{household}/invite-links', [HouseholdController::class, 'storeInviteLink'])->middleware('permission:households.edit')->name('households.invite-links.store');
        Route::delete('households/{household}/invite-links/{link}', [HouseholdController::class, 'destroyInviteLink'])->middleware('permission:households.edit')->name('households.invite-links.destroy');

        // Configuración del hogar (imagen, descripción, módulos, tags)
        Route::get('households/{household}/settings/edit', [HouseholdController::class, 'editSettings'])->middleware('permission:households.edit')->name('households.settings.edit');
        Route::put('households/{household}/settings', [HouseholdController::class, 'updateSettings'])->middleware('permission:households.update')->name('households.settings.update');
        Route::get('households/{household}/image', [HouseholdController::class, 'image'])->middleware('auth')->name('households.image');

        // Wizard de creación (sin permisos restricted — es flujo de setup)
        Route::get('households/{household}/wizard', [HouseholdController::class, 'wizard'])->name('households.wizard');
        Route::post('households/{household}/wizard/complete', [HouseholdController::class, 'wizardComplete'])->name('households.wizard.complete');
        Route::get('households/{household}/wizard/catalog/search', [CatalogController::class, 'searchProvidersWizard'])->name('households.wizard.ai-catalog.search');

        // Configuración IA del hogar
        Route::prefix('households/{household}/settings')->name('households.ai-providers.')->group(function () {
            Route::get('ai-providers', [AiProviderController::class, 'index'])->middleware('permission:households.ai-providers.index')->name('index');
            Route::post('ai-providers', [AiProviderController::class, 'store'])->middleware('permission:households.ai-providers.store')->name('store');
            Route::post('ai-providers/test-config', [AiProviderController::class, 'testConfig'])->middleware('permission:households.ai-providers.test-config')->name('test-config');
            Route::put('ai-providers/{provider}', [AiProviderController::class, 'update'])->middleware('permission:households.ai-providers.update')->name('update');
            Route::delete('ai-providers/{provider}', [AiProviderController::class, 'destroy'])->middleware('permission:households.ai-providers.destroy')->name('destroy');
            Route::post('ai-providers/{provider}/test', [AiProviderController::class, 'test'])->middleware('permission:households.ai-providers.test')->name('test');
            Route::post('ai-providers/{provider}/default', [AiProviderController::class, 'markDefault'])->middleware('permission:households.ai-providers.update')->name('default');
            Route::put('module-config', [AiProviderController::class, 'updateModuleConfig'])->middleware('permission:households.ai-providers.update')->name('module-config');

            // Catálogo models.dev (búsqueda parcial via Inertia)
            Route::get('ai-providers/catalog/search', [CatalogController::class, 'searchProviders'])->middleware('permission:households.ai-providers.index')->name('catalog.search');
            Route::get('ai-providers/catalog/search-models', [CatalogController::class, 'searchModels'])->middleware('permission:households.ai-providers.index')->name('catalog.search-models');
        });

        // === Recursos anidadas bajo household ===
        Route::prefix('households/{household}')->name('households.')->group(function () {

            // Contactos compartidos del hogar (lista estándar en modo tabla)
            Route::get('contacts', [ContactController::class, 'householdIndex'])->middleware('permission:contacts.view')->name('contacts.index');

            // Listas (requiere módulo shopping_lists activo)
            Route::middleware('module:shopping_lists')->group(function () {
                Route::get('lists', [ShoppingListController::class, 'index'])->middleware('permission:lists.index')->name('lists.index');
                Route::get('lists/create', [ShoppingListController::class, 'create'])->middleware('permission:lists.create')->name('lists.create');
                Route::post('lists', [ShoppingListController::class, 'store'])->middleware('permission:lists.store')->name('lists.store');
                Route::get('lists/{list}', [ShoppingListController::class, 'show'])->middleware('permission:lists.show')->scopeBindings()->name('lists.show');
                Route::get('lists/{list}/edit', [ShoppingListController::class, 'edit'])->middleware('permission:lists.edit')->scopeBindings()->name('lists.edit');
                Route::put('lists/{list}', [ShoppingListController::class, 'update'])->middleware('permission:lists.update')->scopeBindings()->name('lists.update');
                Route::delete('lists/{list}', [ShoppingListController::class, 'destroy'])->middleware('permission:lists.destroy')->scopeBindings()->name('lists.destroy');
                Route::post('lists/{list}/items', [ListItemController::class, 'store'])->middleware('permission:lists.items.store')->name('lists.items.store');
                Route::put('lists/{list}/items/{item}', [ListItemController::class, 'update'])->middleware('permission:lists.items.update')->name('lists.items.update');
                Route::delete('lists/{list}/items/{item}', [ListItemController::class, 'destroy'])->middleware('permission:lists.items.destroy')->name('lists.items.destroy');
                Route::get('lists/{list}/items/{item}/image', [ListItemController::class, 'image'])->middleware('auth')->name('lists.items.image');

                // Búsqueda de productos (Inertia partials)
                Route::get('lists/{list}/search-products', [WebProductSearchController::class, 'search'])->middleware('permission:lists.search-products')->scopeBindings()->name('lists.search-products');
                Route::post('lists/{list}/search-products/quick-create', [WebProductSearchController::class, 'quickCreate'])->middleware('permission:lists.search-products.quick-create')->scopeBindings()->name('lists.search-products.quick-create');
            });

            // Recetas compartidas (requiere módulo recipes activo)
            Route::middleware('module:recipes')->group(function () {
                Route::get('recipes', [HouseholdRecipeController::class, 'index'])->middleware('permission:recipes.index')->name('recipes.index');
                Route::post('recipes/{recipe}/share', [HouseholdRecipeController::class, 'share'])->middleware('permission:recipes.share')->name('recipes.share');
                Route::delete('recipes/{recipe}/share', [HouseholdRecipeController::class, 'unshare'])->middleware('permission:recipes.unshare')->name('recipes.unshare');
                Route::post('recipes/{recipe}/add-to-list/{list}', [RecipeShoppingListController::class, 'store'])->middleware('permission:recipes.add-to-list')->name('recipes.add-to-list');
            });

            // Economía de casa (requiere módulo economy activo)
            Route::middleware('module:economy')->group(function () {
                // Transacciones
                Route::get('economy', [EconomicTransactionController::class, 'index'])->middleware('permission:economy.index')->name('economy.index');
                // Ruta fija ANTES del comodín {transaction} para evitar shadowing
                Route::get('economy/imports', [EconomicImportController::class, 'index'])->middleware('permission:economy.imports.index')->name('economy.imports.index');

                Route::get('economy/create', [EconomicTransactionController::class, 'create'])->middleware('permission:economy.create')->name('economy.create');
                Route::post('economy', [EconomicTransactionController::class, 'store'])->middleware('permission:economy.store')->name('economy.store');
                Route::get('economy/{transaction}', [EconomicTransactionController::class, 'show'])->middleware('permission:economy.show')->name('economy.show');
                Route::get('economy/{transaction}/edit', [EconomicTransactionController::class, 'edit'])->middleware('permission:economy.edit')->name('economy.edit');
                Route::post('economy/{transaction}/attachments', [EconomicTransactionAttachmentController::class, 'store'])->middleware('permission:economy.update')->name('economy.attachments.store');
                Route::delete('economy/{transaction}/attachments/{attachment}', [EconomicTransactionAttachmentController::class, 'destroy'])->middleware('permission:economy.update')->name('economy.attachments.destroy');
                Route::get('economy/{transaction}/attachments/{attachment}/file', [EconomicTransactionAttachmentController::class, 'file'])->middleware('permission:economy.show')->name('economy.attachments.file');
                Route::put('economy/{transaction}', [EconomicTransactionController::class, 'update'])->middleware('permission:economy.update')->name('economy.update');
                Route::delete('economy/{transaction}', [EconomicTransactionController::class, 'destroy'])->middleware('permission:economy.destroy')->name('economy.destroy');

                // Documentos
                Route::post('economy/documents', [EconomicDocumentController::class, 'store'])->middleware('permission:economy.create')->name('economy.documents.store');
                Route::delete('economy/documents/{document}', [EconomicDocumentController::class, 'destroy'])->middleware('permission:economy.destroy')->name('economy.documents.destroy');
                Route::get('economy/documents/{document}/file', [EconomicDocumentController::class, 'file'])->middleware('permission:economy.show')->name('economy.documents.file');

                // Importaciones
                Route::get('economy/imports/create', [EconomicImportController::class, 'create'])->middleware('permission:economy.create')->name('economy.imports.create');
                Route::post('economy/imports', [EconomicImportController::class, 'store'])->middleware('permission:households.economy.imports.store')->name('economy.imports.store');
                Route::get('economy/imports/{import}', [EconomicImportController::class, 'show'])->middleware('permission:economy.show')->name('economy.imports.show');
                Route::post('economy/imports/{import}/confirm', [EconomicImportController::class, 'confirm'])->middleware('permission:economy.store')->name('economy.imports.confirm');
                Route::post('economy/imports/{import}/retry', [EconomicImportController::class, 'retry'])->middleware('permission:economy.create')->name('economy.imports.retry');
                Route::post('economy/imports/{import}/reprocess', [EconomicImportController::class, 'reprocess'])->middleware('permission:economy.create')->name('economy.imports.reprocess');
                Route::post('economy/imports/{import}/discard', [EconomicImportController::class, 'discard'])->middleware('permission:economy.destroy')->name('economy.imports.discard');
            });

            // Productos
            Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.store')->name('products.store');
            Route::post('products/quick-create', [ProductSearchController::class, 'quickCreate'])->middleware('permission:products.store')->name('products.quick-create');

            // Productos personales
            Route::post('personal-products', [PersonalProductController::class, 'store'])->name('personal-products.store');
        });

        // === Assistant ===
        Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
        Route::post('assistant', [AssistantController::class, 'store'])->name('assistant.store');
        Route::get('assistant/proposals', [AssistantController::class, 'proposals'])->name('assistant.proposals');
        Route::get('assistant/{conversation}', [AssistantController::class, 'show'])->name('assistant.show');
        Route::delete('assistant/{conversation}', [AssistantController::class, 'destroy'])->name('assistant.destroy');
        Route::post('assistant/{conversation}/message', [AssistantController::class, 'sendMessage'])->name('assistant.send-message');
        Route::post('assistant/{conversation}/recipes/{recipeRun}', [AssistantController::class, 'createRecipe'])->name('assistant.create-recipe');
        Route::post('assistant/proposals/{proposal}/accept', [AssistantController::class, 'acceptProposal'])->name('assistant.accept-proposal');
        Route::post('assistant/proposals/{proposal}/reject', [AssistantController::class, 'rejectProposal'])->name('assistant.reject-proposal');
    });

    // === Job Monitoring (workers de cola) ===
    Route::prefix('jobs-monitor')->name('jobs-monitor.')->group(function () {
        Route::get('/', [JobMonitoringController::class, 'index'])->middleware('permission:jobs-monitor.index')->name('index');
        Route::get('jobs', [JobMonitoringController::class, 'jobs'])->middleware('permission:jobs-monitor.jobs')->name('jobs');
        Route::get('jobs/{id}', [JobMonitoringController::class, 'show'])->middleware('permission:jobs-monitor.show')->name('show');
        Route::post('jobs/{id}/retry', [JobMonitoringController::class, 'retry'])->middleware('permission:jobs-monitor.retry')->name('retry');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Invitación (sin auth, con URL firmada)
Route::get('invitation/{user}', [InvitationController::class, 'show'])->middleware(['signed'])->name('invitation.show');
Route::post('invitation', [InvitationController::class, 'store'])->middleware(['signed'])->name('invitation.store');

// OTP Setup (con URL firmada, sin auth - parte del flujo de onboarding)
Route::get('otp/setup/{user}', [OtpSetupController::class, 'show'])->middleware(['signed'])->name('otp.setup');
Route::post('otp/confirm/{user}', [OtpSetupController::class, 'confirm'])->middleware(['signed', 'throttle:otp'])->name('otp.confirm');
