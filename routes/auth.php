<?php

use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotOtpController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetOtpController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

// Guest routes (sin autenticar)
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:10,1')->name('google.callback');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('reset-password/{user}', [ResetPasswordController::class, 'show'])->middleware(['signed'])->name('password.reset');
    Route::post('reset-password/{user}', [ResetPasswordController::class, 'store'])->middleware(['signed'])->name('password.update');
    Route::get('forgot-otp', [ForgotOtpController::class, 'show'])->name('otp.forgot');
    Route::post('forgot-otp', [ForgotOtpController::class, 'store'])->name('otp.forgot.store');
    Route::get('reset-otp/{user}', [ResetOtpController::class, 'show'])->middleware(['signed'])->name('otp.reset');
    Route::post('reset-otp/{user}', [ResetOtpController::class, 'confirm'])->middleware(['signed', 'throttle:otp'])->name('otp.reset.confirm');
});

Route::get('google/otp/setup', [GoogleAuthController::class, 'showOtpSetup'])->name('google.otp.setup');
Route::post('google/otp/setup', [GoogleAuthController::class, 'confirmOtpSetup'])->middleware('throttle:otp')->name('google.otp.confirm');

// Two-factor challenge (no requiere auth, pero sí session login.id)
Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.login');
Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:otp')->name('two-factor.login.store');

// Auth routes (con autenticar)
Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('verify-email', [EmailVerificationController::class, 'show'])->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'store'])->name('verification.send');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed'])
        ->name('verification.verify');
    Route::get('confirm-password', [ConfirmPasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmPasswordController::class, 'store'])->name('password.confirm.store');
});
