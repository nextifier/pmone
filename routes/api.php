<?php

use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Basic authenticated routes
Route::middleware(['auth:sanctum'])->get('/user', [UserController::class, 'profile']);

// Protected API routes (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Media endpoints
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::post('/bulk-upload', [MediaController::class, 'bulkUpload']);
        Route::delete('/bulk-delete', [MediaController::class, 'bulkDelete']);
        Route::delete('/{media}', [MediaController::class, 'delete']);
    });

    // User management endpoints
    Route::prefix('user')->group(function () {
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::patch('/settings', [UserController::class, 'updateSettings']);
        Route::patch('/links', [UserController::class, 'updateLinks']);
        Route::get('/password-status', [UserController::class, 'passwordStatus']);
        Route::put('/password', [UserController::class, 'updatePassword']);
    });

    // User administration endpoints (master and admin only)
    Route::prefix('users')->middleware('can:users.view')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('can:users.create');
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::delete('/bulk', [UserController::class, 'bulkDestroy'])->middleware('can:users.delete');
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
