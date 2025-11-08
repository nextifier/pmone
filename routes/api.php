<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ShortLinkController;
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

    // Role management endpoints (master only)
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::post('/', [RoleController::class, 'store']);
        Route::delete('/bulk', [RoleController::class, 'bulkDestroy']);
        Route::get('/{name}', [RoleController::class, 'show']);
        Route::put('/{name}', [RoleController::class, 'update']);
        Route::delete('/{name}', [RoleController::class, 'destroy']);
    });

    // Permission endpoints (master only)
    Route::get('/permissions', [RoleController::class, 'permissions']);

    // Project management endpoints
    Route::prefix('projects')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/export', [ProjectController::class, 'export'])->name('projects.export');
        Route::post('/import', [ProjectController::class, 'import'])->name('projects.import');
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

    // Short link management endpoints
    Route::prefix('short-links')->group(function () {
        Route::get('/', [ShortLinkController::class, 'index'])->name('short-links.index');
        Route::post('/', [ShortLinkController::class, 'store'])->name('short-links.store');
        Route::get('/export', [ShortLinkController::class, 'export'])->name('short-links.export');
        Route::post('/import', [ShortLinkController::class, 'import'])->name('short-links.import');
        Route::get('/import/template', [ShortLinkController::class, 'downloadTemplate'])->name('short-links.import.template');
        Route::delete('/bulk', [ShortLinkController::class, 'bulkDestroy'])->name('short-links.bulk-destroy');
        Route::get('/trash', [ShortLinkController::class, 'trash'])->name('short-links.trash');
        Route::post('/trash/restore/bulk', [ShortLinkController::class, 'bulkRestore'])->name('short-links.bulk-restore');
        Route::post('/trash/{id}/restore', [ShortLinkController::class, 'restore'])->name('short-links.restore');
        Route::delete('/trash/bulk', [ShortLinkController::class, 'bulkForceDestroy'])->name('short-links.bulk-force-destroy');
        Route::delete('/trash/{id}', [ShortLinkController::class, 'forceDestroy'])->name('short-links.force-destroy');
        Route::get('/{shortLink:slug}', [ShortLinkController::class, 'show'])->name('short-links.show');
        Route::put('/{shortLink:slug}', [ShortLinkController::class, 'update'])->name('short-links.update');
        Route::delete('/{shortLink:slug}', [ShortLinkController::class, 'destroy'])->name('short-links.destroy');
        Route::get('/{shortLink:slug}/analytics', [ShortLinkController::class, 'getAnalytics'])->name('short-links.analytics');
    });
});

// Public API routes (no authentication required)
Route::prefix('users')->group(function () {
    Route::get('/{user:username}', [ProfileController::class, 'getUserProfile'])
        ->middleware('throttle:api');
});

// Project profiles (public)
Route::prefix('projects')->group(function () {
    Route::get('/{username}', [ProfileController::class, 'getProjectProfile'])
        ->middleware('throttle:api');
});

// Short link resolution (public)
Route::get('/s/{slug}', [ProfileController::class, 'resolveShortLink'])
    ->middleware('throttle:api');

// Tracking routes (public - can track anonymous visitors)
Route::post('/track/click', [TrackingController::class, 'trackLinkClick'])->middleware('throttle:api');
Route::post('/track/visit', [TrackingController::class, 'trackProfileVisit'])->middleware('throttle:api');

// Analytics routes (authenticated)
Route::middleware(['auth:sanctum'])->prefix('analytics')->group(function () {
    Route::get('/visits', [AnalyticsController::class, 'getVisits']);
    Route::get('/clicks', [AnalyticsController::class, 'getClicks']);
    Route::get('/summary', [AnalyticsController::class, 'getSummary']);
    Route::get('/activity-log', [AnalyticsController::class, 'getActivityLog']);
});

// Google Analytics (GA4) routes (authenticated + admin/master only)
Route::middleware(['auth:sanctum'])->prefix('google-analytics')->group(function () {
    // GA Properties Import/Export (must be before {id} routes)
    Route::get('/ga-properties/export', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'export']);
    Route::get('/ga-properties/import/template', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'downloadTemplate']);
    Route::post('/ga-properties/import', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'import']);

    // CRUD endpoints for GA properties
    Route::get('/ga-properties', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'index']);
    Route::post('/ga-properties', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'store']);
    Route::get('/ga-properties/{id}', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'show']);
    Route::put('/ga-properties/{id}', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'update']);
    Route::delete('/ga-properties/{id}', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'destroy']);

    // Analytics data endpoints
    Route::get('/properties', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getProperties']);
    Route::get('/properties/{id}/analytics', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getPropertyAnalytics']);
    Route::get('/aggregate', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getAggregatedAnalytics']);
    Route::post('/sync', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'sync']);
    Route::post('/aggregate', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'aggregate']);
    Route::post('/aggregate/sync-now', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'triggerAggregateSyncNow']);
    Route::get('/cache/status', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getCacheStatus']);
    Route::delete('/cache/properties/{id}', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'clearPropertyCache']);
    Route::delete('/cache/all', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'clearAllCache']);

    // Sync logs endpoints
    Route::get('/sync-logs', [\App\Http\Controllers\Api\AnalyticsSyncLogController::class, 'index']);
    Route::get('/sync-logs/stats', [\App\Http\Controllers\Api\AnalyticsSyncLogController::class, 'stats']);
});
