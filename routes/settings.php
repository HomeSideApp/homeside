<?php

use App\Http\Controllers\Settings\GoogleIdentityController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserAiProviderController;
use App\Http\Controllers\Web\CatalogController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/google/connect', [GoogleIdentityController::class, 'redirect'])->middleware(RequirePassword::class)->name('google.link.redirect');
    Route::get('settings/google/callback', [GoogleIdentityController::class, 'callback'])->name('google.link.callback');
    Route::delete('settings/google', [GoogleIdentityController::class, 'destroy'])->middleware(RequirePassword::class)->name('google.link.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::redirect('settings/appearance', '/settings/profile');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::post('settings/security/reset-otp', [SecurityController::class, 'resetOtp'])
        ->middleware(RequirePassword::class)
        ->name('security.otp.reset');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::prefix('settings/ai-providers')->name('settings.ai-providers.')->controller(UserAiProviderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('test-config', 'testConfig')->name('test-config');
        Route::put('{provider}', 'update')->name('update');
        Route::delete('{provider}', 'destroy')->name('destroy');
        Route::post('{provider}/test', 'test')->name('test');
        Route::post('{provider}/default', 'markDefault')->name('default');

        // Catálogo models.dev (búsqueda parcial via Inertia)
        Route::get('catalog/search', [CatalogController::class, 'searchProvidersPersonal'])->name('catalog.search');
    });
});
