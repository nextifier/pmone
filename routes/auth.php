<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

// Guest routes (Unauthenticated users)
Route::middleware('guest')->group(function () {
    // Standard authentication
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    // Password reset
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // OAuth authentication
    Route::prefix('auth')->group(function () {
        Route::get('/{provider}/redirect', [OAuthController::class, 'redirect'])
            ->where('provider', 'google|github')
            ->name('oauth.redirect');
        Route::get('/{provider}/callback', [OAuthController::class, 'callback'])
            ->where('provider', 'google|github')
            ->name('oauth.callback');
    });

    // Magic link authentication
    Route::post('/auth/magic-link', [MagicLinkController::class, 'sendMagicLink'])
        ->name('magic-link.send');
});

// Public routes (No authentication required)
Route::get('/auth/magic-link/{token}', [MagicLinkController::class, 'loginWithMagicLink'])
    ->name('magic-link.verify');

// Password reset form (redirect to frontend)
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset');

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Session management
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Email verification
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Signed routes
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');
