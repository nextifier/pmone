<?php

use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\UploadController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API routes
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// Basic authenticated routes
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    $user = $request->user()->load(['roles', 'permissions', 'oauthProviders', 'media']);

    return (new UserResource($user))->resolve();
});

// Protected API routes (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // File upload endpoints
    Route::prefix('upload')->group(function () {
        Route::post('/', [UploadController::class, 'upload']);
        Route::post('/profile-image', [UploadController::class, 'uploadProfileImage']);
        Route::post('/cover-image', [UploadController::class, 'uploadCoverImage']);
        Route::delete('/media/{media}', [UploadController::class, 'deleteMedia']);
    });

    // User management endpoints
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'me']);
        Route::put('/profile/update', [UserController::class, 'updateProfile']);
        Route::patch('/profile/settings', [UserController::class, 'updateSettings']);
        Route::patch('/profile/links', [UserController::class, 'updateLinks']);
        Route::get('/password-status', [UserController::class, 'passwordStatus']);
        Route::put('/password', [UserController::class, 'updatePassword']);
    });

    // User administration endpoints (master and admin only)
    Route::prefix('users')->middleware('can:users.view')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('can:users.create');
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');
    });

    // Log management endpoints (master and admin only)
    Route::prefix('logs')->group(function () {
        Route::get('/', [LogController::class, 'index']);
        Route::get('/log-names', [LogController::class, 'logNames']);
        Route::get('/events', [LogController::class, 'events']);
        Route::delete('/clear', [LogController::class, 'clear']);
    });
});

// Public API routes (no authentication required)
Route::prefix('user')->group(function () {
    Route::get('/{user:username}', [UserController::class, 'showByUsername'])
        ->middleware('throttle:api');
});
