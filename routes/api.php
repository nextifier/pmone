<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TemporaryUploadController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Basic authenticated routes
Route::middleware(['auth:sanctum'])->get('/user', [UserController::class, 'profile']);

// Protected API routes (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Temporary upload endpoints (for FilePond)
    Route::prefix('tmp-upload')->group(function () {
        Route::post('/', [TemporaryUploadController::class, 'upload']);
        Route::delete('/', [TemporaryUploadController::class, 'revert']);
        Route::get('/load', [TemporaryUploadController::class, 'load']);
        Route::get('/metadata', [TemporaryUploadController::class, 'metadata']);
    });

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
        Route::get('/export', [UserController::class, 'export']);
        Route::get('/import/template', [UserController::class, 'downloadTemplate'])->middleware('can:users.create');
        Route::post('/import', [UserController::class, 'import'])->middleware('can:users.create');
        Route::delete('/bulk', [UserController::class, 'bulkDestroy'])->middleware('can:users.delete');
        Route::get('/trash', [UserController::class, 'trash']);
        Route::post('/trash/restore/bulk', [UserController::class, 'bulkRestore'])->middleware('can:users.delete');
        Route::post('/trash/{id}/restore', [UserController::class, 'restore'])->middleware('can:users.delete');
        Route::delete('/trash/bulk', [UserController::class, 'bulkForceDestroy'])->middleware('can:users.delete');
        Route::delete('/trash/{id}', [UserController::class, 'forceDestroy'])->middleware('can:users.delete');
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.edit');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete');
    });

    // Project management endpoints
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/export', [ProjectController::class, 'export'])->name('projects.export');
        Route::get('/import/template', [ProjectController::class, 'downloadTemplate'])->name('projects.import.template');
        Route::post('/update-order', [ProjectController::class, 'updateOrder'])->name('projects.update-order');
        Route::get('/eligible-members', [ProjectController::class, 'getEligibleMembers'])->name('projects.eligible-members');
        Route::delete('/bulk', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
        Route::get('/trash', [ProjectController::class, 'trash'])->name('projects.trash');
        Route::post('/trash/restore/bulk', [ProjectController::class, 'bulkRestore'])->name('projects.bulk-restore');
        Route::post('/trash/{id}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
        Route::delete('/trash/bulk', [ProjectController::class, 'bulkForceDestroy'])->name('projects.bulk-force-destroy');
        Route::delete('/trash/{id}', [ProjectController::class, 'forceDestroy'])->name('projects.force-destroy');
        Route::get('/{username}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('/{username}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/{username}', [ProjectController::class, 'destroy'])->name('projects.destroy');
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

// Profile routes (public)
Route::get('/@{username}', [ProfileController::class, 'getUserProfile'])->middleware('throttle:api');
Route::get('/p/{username}', [ProfileController::class, 'getProjectProfile'])->middleware('throttle:api');
Route::get('/{slug}', [ProfileController::class, 'resolveShortLink'])->middleware('throttle:api');

// Tracking routes (public - can track anonymous visitors)
Route::post('/track/click', [TrackingController::class, 'trackLinkClick'])->middleware('throttle:api');

// Analytics routes (authenticated)
Route::middleware(['auth:sanctum'])->prefix('analytics')->group(function () {
    Route::get('/visits', [AnalyticsController::class, 'getVisits']);
    Route::get('/clicks', [AnalyticsController::class, 'getClicks']);
    Route::get('/summary', [AnalyticsController::class, 'getSummary']);
    Route::get('/activity-log', [AnalyticsController::class, 'getActivityLog']);
});
