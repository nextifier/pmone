<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ApiConsumerController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\BrandEventController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactFormController;
use App\Http\Controllers\Api\ContactFormSubmissionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventProductController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExhibitorDashboardController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PostAutosaveController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectActivityController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectCustomFieldController;
use App\Http\Controllers\Api\PublicBlogController;
use App\Http\Controllers\Api\PublicProjectController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ShortLinkController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TemporaryUploadController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Middlewares\CacheResponse;

// Basic authenticated routes
Route::middleware(['auth:sanctum'])->get('/user', [UserController::class, 'profile']);

// Dashboard routes (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/writer-stats', [DashboardController::class, 'writerStats'])->name('dashboard.writer-stats');
});

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
        Route::get('/{media}/download', [MediaController::class, 'download']);
        Route::delete('/{media}', [MediaController::class, 'delete']);
    });

    // Temporary media serving and deletion endpoints
    Route::get('/tmp-media/{folder}', [MediaController::class, 'serveTempMedia']);
    Route::delete('/tmp-media/{folder}', [MediaController::class, 'deleteTempMedia']);

    // User management endpoints
    Route::prefix('user')->group(function () {
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::patch('/settings', [UserController::class, 'updateSettings']);
        Route::patch('/links', [UserController::class, 'updateLinks']);
        Route::get('/password-status', [UserController::class, 'passwordStatus']);
        Route::put('/password', [UserController::class, 'updatePassword']);
    });

    // User administration endpoints (master and admin only)
    Route::prefix('users')->middleware('can:users.read')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store'])->middleware('can:users.create');
        Route::get('/roles', [UserController::class, 'getRoles']);
        Route::get('/export', [UserController::class, 'export']);
        Route::get('/import/template', [UserController::class, 'downloadTemplate'])->middleware('can:users.create');
        Route::post('/import', [UserController::class, 'import'])->middleware('can:users.create');
        Route::delete('/bulk', [UserController::class, 'bulkDestroy'])->middleware('can:users.delete');
        Route::post('/verify/bulk', [UserController::class, 'bulkVerify'])->middleware('can:users.update');
        Route::post('/unverify/bulk', [UserController::class, 'bulkUnverify'])->middleware('can:users.update');
        Route::get('/trash', [UserController::class, 'trash']);
        Route::post('/trash/restore/bulk', [UserController::class, 'bulkRestore'])->middleware('can:users.delete');
        Route::post('/trash/{id}/restore', [UserController::class, 'restore'])->middleware('can:users.delete');
        Route::delete('/trash/bulk', [UserController::class, 'bulkForceDestroy'])->middleware('can:users.delete');
        Route::delete('/trash/{id}', [UserController::class, 'forceDestroy'])->middleware('can:users.delete');
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.update');
        Route::post('/{user}/verify', [UserController::class, 'verify'])->middleware('can:users.update');
        Route::post('/{user}/unverify', [UserController::class, 'unverify'])->middleware('can:users.update');
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

    // Permission management endpoints
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionController::class, 'index']);
        Route::get('/grouped', [PermissionController::class, 'grouped']);
        Route::post('/', [PermissionController::class, 'store']);
        Route::delete('/bulk', [PermissionController::class, 'bulkDestroy']);
        Route::get('/{id}', [PermissionController::class, 'show']);
        Route::put('/{id}', [PermissionController::class, 'update']);
        Route::delete('/{id}', [PermissionController::class, 'destroy']);
    });

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
        Route::post('/{username}/members/toggle', [ProjectController::class, 'toggleMember'])->name('projects.toggle-member');
        Route::get('/{username}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('/{username}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/{username}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    });

    // Project activity endpoint
    Route::get('/projects/{username}/activity', [ProjectActivityController::class, 'index'])->name('projects.activity');

    // Project custom fields endpoints
    Route::prefix('projects/{username}/custom-fields')->group(function () {
        Route::get('/', [ProjectCustomFieldController::class, 'index'])->name('projects.custom-fields.index');
        Route::post('/', [ProjectCustomFieldController::class, 'store'])->name('projects.custom-fields.store');
        Route::put('/reorder', [ProjectCustomFieldController::class, 'reorder'])->name('projects.custom-fields.reorder');
        Route::put('/{id}', [ProjectCustomFieldController::class, 'update'])->name('projects.custom-fields.update');
        Route::delete('/{id}', [ProjectCustomFieldController::class, 'destroy'])->name('projects.custom-fields.destroy');
    });

    // Event management endpoints (nested under projects)
    Route::prefix('projects/{username}/events')->group(function () {
        Route::get('/', [EventController::class, 'index'])->name('events.index');
        Route::post('/', [EventController::class, 'store'])->name('events.store');
        Route::post('/update-order', [EventController::class, 'updateOrder'])->name('events.update-order');
        Route::get('/trash', [EventController::class, 'trash'])->name('events.trash');
        Route::post('/trash/{id}/restore', [EventController::class, 'restore'])->name('events.restore');
        Route::delete('/trash/{id}', [EventController::class, 'forceDestroy'])->name('events.force-destroy');
        Route::get('/{eventSlug}', [EventController::class, 'show'])->name('events.show');
        Route::put('/{eventSlug}', [EventController::class, 'update'])->name('events.update');
        Route::post('/{eventSlug}/set-active', [EventController::class, 'setActive'])->name('events.set-active');
        Route::delete('/{eventSlug}', [EventController::class, 'destroy'])->name('events.destroy');
    });

    // Event product management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/products')->group(function () {
        Route::get('/', [EventProductController::class, 'index'])->name('event-products.index');
        Route::post('/', [EventProductController::class, 'store'])->name('event-products.store');
        Route::post('/reorder', [EventProductController::class, 'reorder'])->name('event-products.reorder');
        Route::get('/categories', [EventProductController::class, 'categories'])->name('event-products.categories');
        Route::get('/export', [EventProductController::class, 'export'])->name('event-products.export');
        Route::get('/import/template', [EventProductController::class, 'downloadTemplate'])->name('event-products.import.template');
        Route::post('/import', [EventProductController::class, 'import'])->name('event-products.import');
        Route::get('/{id}', [EventProductController::class, 'show'])->name('event-products.show');
        Route::put('/{id}', [EventProductController::class, 'update'])->name('event-products.update');
        Route::delete('/{id}', [EventProductController::class, 'destroy'])->name('event-products.destroy');
    });

    // Order management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/{ulid}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('/{ulid}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::patch('/{ulid}/discount', [OrderController::class, 'applyDiscount'])->name('orders.apply-discount');
    });

    // Brand management endpoints (nested under project events)
    Route::prefix('projects/{username}/events/{eventSlug}/brands')->group(function () {
        Route::get('/', [BrandEventController::class, 'index'])->name('brand-events.index');
        Route::post('/', [BrandEventController::class, 'store'])->name('brand-events.store');
        Route::get('/export', [BrandEventController::class, 'export'])->name('brand-events.export');
        Route::get('/import/template', [BrandEventController::class, 'downloadTemplate'])->name('brand-events.import.template');
        Route::post('/import', [BrandEventController::class, 'import'])->name('brand-events.import');
        Route::post('/update-order', [BrandEventController::class, 'updateOrder'])->name('brand-events.update-order');
        Route::get('/{brandSlug}', [BrandEventController::class, 'show'])->name('brand-events.show');
        Route::put('/{brandSlug}', [BrandEventController::class, 'update'])->name('brand-events.update');
        Route::put('/{brandSlug}/profile', [BrandEventController::class, 'updateProfile'])->name('brand-events.update-profile');
        Route::delete('/{brandSlug}', [BrandEventController::class, 'destroy'])->name('brand-events.destroy');
        // Members
        Route::get('/{brandSlug}/members', [BrandEventController::class, 'members'])->name('brand-events.members.index');
        Route::post('/{brandSlug}/members', [BrandEventController::class, 'addMember'])->name('brand-events.members.store');
        Route::delete('/{brandSlug}/members/{userId}', [BrandEventController::class, 'removeMember'])->name('brand-events.members.destroy');
        Route::post('/{brandSlug}/members/{userId}/send-invite', [BrandEventController::class, 'sendInvite'])->name('brand-events.members.send-invite');
        // Promotion posts
        Route::get('/{brandSlug}/promotion-posts', [BrandEventController::class, 'promotionPosts'])->name('brand-events.promotion-posts.index');
        Route::post('/{brandSlug}/promotion-posts', [BrandEventController::class, 'storePromotionPost'])->name('brand-events.promotion-posts.store');
        Route::post('/{brandSlug}/promotion-posts/update-order', [BrandEventController::class, 'updatePromotionPostOrder'])->name('brand-events.promotion-posts.update-order');
        Route::put('/{brandSlug}/promotion-posts/{postId}', [BrandEventController::class, 'updatePromotionPost'])->name('brand-events.promotion-posts.update');
        Route::delete('/{brandSlug}/promotion-posts/{postId}', [BrandEventController::class, 'destroyPromotionPost'])->name('brand-events.promotion-posts.destroy');
        Route::post('/{brandSlug}/promotion-posts/{postId}/reorder-media', [BrandEventController::class, 'reorderPromotionPostMedia'])->name('brand-events.promotion-posts.reorder-media');
    });

    // Global orders route (staff+ see all, exhibitors see their own)
    Route::get('/orders', [OrderController::class, 'all'])->name('orders.all');

    // Global brand routes (staff+)
    Route::get('/brands', [BrandController::class, 'index'])->middleware('can:brands.read')->name('brands.list');
    Route::get('/brands/export', [BrandController::class, 'export'])->middleware('can:brands.read')->name('brands.export');
    Route::get('/brands/import/template', [BrandController::class, 'downloadTemplate'])->middleware('can:brands.create')->name('brands.import.template');
    Route::post('/brands/import', [BrandController::class, 'import'])->middleware('can:brands.create')->name('brands.import');
    Route::get('/brands/search', [BrandController::class, 'search'])->name('brands.search');
    Route::get('/brands/{brand}', [BrandController::class, 'show'])->middleware('can:brands.read')->name('brands.show');
    Route::put('/brands/{brand}', [BrandController::class, 'update'])->middleware('can:brands.update')->name('brands.update');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->middleware('can:brands.delete')->name('brands.delete');

    // Notification endpoints
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    });

    // Exhibitor dashboard endpoints
    Route::prefix('exhibitor')->group(function () {
        Route::get('/dashboard', [ExhibitorDashboardController::class, 'dashboard'])->name('exhibitor.dashboard');
        Route::get('/brands', [ExhibitorDashboardController::class, 'brands'])->name('exhibitor.brands');
        Route::get('/brands/{brandSlug}', [ExhibitorDashboardController::class, 'brandShow'])->name('exhibitor.brands.show');
        Route::put('/brands/{brandSlug}', [ExhibitorDashboardController::class, 'brandUpdate'])->name('exhibitor.brands.update');
        Route::get('/brands/{brandSlug}/events', [ExhibitorDashboardController::class, 'brandEvents'])->name('exhibitor.brands.events');

        // Order form endpoints
        Route::get('/brands/{brandSlug}/events/{brandEventId}/products', [ExhibitorDashboardController::class, 'orderFormProducts'])->name('exhibitor.order-form.products');
        Route::get('/brands/{brandSlug}/events/{brandEventId}/order-form-info', [ExhibitorDashboardController::class, 'orderFormInfo'])->name('exhibitor.order-form.info');
        Route::post('/brands/{brandSlug}/events/{brandEventId}/orders', [ExhibitorDashboardController::class, 'submitOrder'])->name('exhibitor.orders.store');
        Route::get('/brands/{brandSlug}/events/{brandEventId}/orders', [ExhibitorDashboardController::class, 'myOrders'])->name('exhibitor.orders.index');
        Route::get('/brands/{brandSlug}/events/{brandEventId}/orders/{ulid}', [ExhibitorDashboardController::class, 'myOrderShow'])->name('exhibitor.orders.show');

        Route::get('/brands/{brandSlug}/events/{brandEventId}/promotion-posts', [ExhibitorDashboardController::class, 'promotionPosts'])->name('exhibitor.promotion-posts.index');
        Route::post('/brands/{brandSlug}/events/{brandEventId}/promotion-posts', [ExhibitorDashboardController::class, 'storePromotionPost'])->name('exhibitor.promotion-posts.store');
        Route::put('/brands/{brandSlug}/events/{brandEventId}/promotion-posts/{postId}', [ExhibitorDashboardController::class, 'updatePromotionPost'])->name('exhibitor.promotion-posts.update');
        Route::delete('/brands/{brandSlug}/events/{brandEventId}/promotion-posts/{postId}', [ExhibitorDashboardController::class, 'destroyPromotionPost'])->name('exhibitor.promotion-posts.destroy');
        Route::post('/brands/{brandSlug}/events/{brandEventId}/promotion-posts/{postId}/reorder-media', [ExhibitorDashboardController::class, 'reorderPromotionPostMedia'])->name('exhibitor.promotion-posts.reorder-media');
    });

    // Task management endpoints
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/all', [TaskController::class, 'all'])->name('tasks.all');
        Route::post('/update-order', [TaskController::class, 'updateOrder'])->name('tasks.update-order');
        Route::post('/', [TaskController::class, 'store'])->name('tasks.store');
        Route::delete('/bulk', [TaskController::class, 'bulkDestroy'])->name('tasks.bulk-destroy');
        Route::get('/trash', [TaskController::class, 'trash'])->name('tasks.trash');
        Route::post('/trash/restore/bulk', [TaskController::class, 'bulkRestore'])->name('tasks.bulk-restore');
        Route::post('/trash/{id}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
        Route::delete('/trash/bulk', [TaskController::class, 'bulkForceDestroy'])->name('tasks.bulk-force-destroy');
        Route::delete('/trash/{id}', [TaskController::class, 'forceDestroy'])->name('tasks.force-destroy');
        Route::get('/{task:ulid}', [TaskController::class, 'show'])->name('tasks.show');
        Route::put('/{task:ulid}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/{task:ulid}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    });

    // User tasks endpoint (view tasks by username)
    Route::get('/users/{username}/tasks', [TaskController::class, 'userTasks'])->name('tasks.user');

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

    // Contact form submission management (inbox)
    Route::prefix('contact-form-submissions')->group(function () {
        Route::get('/', [ContactFormSubmissionController::class, 'index'])->name('contact-form-submissions.index');
        Route::get('/export', [ContactFormSubmissionController::class, 'export'])->name('contact-form-submissions.export');
        Route::get('/import/template', [ContactFormSubmissionController::class, 'downloadTemplate'])->name('contact-form-submissions.import.template');
        Route::post('/import', [ContactFormSubmissionController::class, 'import'])->name('contact-form-submissions.import');
        Route::delete('/bulk', [ContactFormSubmissionController::class, 'bulkDestroy'])->name('contact-form-submissions.bulk-destroy');
        Route::get('/trash', [ContactFormSubmissionController::class, 'trash'])->name('contact-form-submissions.trash');
        Route::post('/trash/restore/bulk', [ContactFormSubmissionController::class, 'bulkRestore'])->name('contact-form-submissions.bulk-restore');
        Route::post('/trash/{id}/restore', [ContactFormSubmissionController::class, 'restore'])->name('contact-form-submissions.restore');
        Route::delete('/trash/bulk', [ContactFormSubmissionController::class, 'bulkForceDelete'])->name('contact-form-submissions.bulk-force-delete');
        Route::delete('/trash/{id}', [ContactFormSubmissionController::class, 'forceDelete'])->name('contact-form-submissions.force-delete');
        Route::get('/{contactFormSubmission:ulid}', [ContactFormSubmissionController::class, 'show'])->name('contact-form-submissions.show');
        Route::patch('/{contactFormSubmission:ulid}/status', [ContactFormSubmissionController::class, 'updateStatus'])->name('contact-form-submissions.update-status');
        Route::patch('/{contactFormSubmission:ulid}/follow-up', [ContactFormSubmissionController::class, 'markAsFollowedUp'])->name('contact-form-submissions.follow-up');
        Route::delete('/{contactFormSubmission:ulid}', [ContactFormSubmissionController::class, 'destroy'])->name('contact-form-submissions.destroy');
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

// Unified slug resolution (user profile or short link)
Route::get('/resolve/{slug}', [ProfileController::class, 'resolveSlug'])
    ->middleware('throttle:api');

// Short link resolution (public - backward compatibility)
Route::get('/s/{slug}', [ProfileController::class, 'resolveShortLink'])
    ->middleware('throttle:api');

// Tracking routes (public - can track anonymous visitors)
Route::post('/track/click', [TrackingController::class, 'trackLinkClick'])->middleware('throttle:api');
Route::post('/track/visit', [TrackingController::class, 'trackProfileVisit'])->middleware('throttle:api');

// Contact form submission (requires API key for CORS and rate limiting)
Route::post('/contact-forms/submit', [ContactFormController::class, 'submit'])->middleware(['api.key']);

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
    Route::get('/properties/{id}/analytics/export', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'exportPropertyAnalytics']);
    Route::get('/aggregate', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getAggregatedAnalytics']);
    Route::get('/aggregate/export', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'exportAggregatedAnalytics']);
    Route::get('/realtime', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getRealtimeActiveUsers']);
    Route::post('/sync', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'sync']);
    Route::post('/aggregate', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'aggregate']);
    Route::post('/aggregate/sync-now', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'triggerAggregateSyncNow']);
    Route::get('/cache/status', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'getCacheStatus']);
    Route::delete('/cache/properties/{id}', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'clearPropertyCache']);
    Route::delete('/cache/all', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'clearAllCache']);
    Route::delete('/rate-limit', [\App\Http\Controllers\Api\GoogleAnalyticsController::class, 'clearRateLimit']);

    // Sync logs endpoints
    Route::get('/sync-logs', [\App\Http\Controllers\Api\AnalyticsSyncLogController::class, 'index']);
    Route::get('/sync-logs/stats', [\App\Http\Controllers\Api\AnalyticsSyncLogController::class, 'stats']);
});

// Public post endpoints
// Post management endpoints (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('posts')->group(function () {
    Route::get('/', [PostController::class, 'index'])->name('posts.index');
    Route::post('/', [PostController::class, 'store'])->name('posts.store');
    Route::get('/check-slug', [PostController::class, 'checkSlug'])->name('posts.check-slug');
    Route::get('/eligible-authors', [PostController::class, 'eligibleAuthors'])->name('posts.eligible-authors');
    Route::get('/analytics', [PostController::class, 'overallAnalytics'])->name('posts.overall-analytics');
    Route::get('/export', [PostController::class, 'export'])->name('posts.export');
    Route::get('/export/with-images', [PostController::class, 'exportWithImages'])->name('posts.export-with-images');
    Route::delete('/bulk', [PostController::class, 'bulkDestroy'])->name('posts.bulk-destroy');
    Route::post('/bulk/status', [PostController::class, 'bulkUpdateStatus'])->name('posts.bulk-update-status');
    Route::get('/trash', [PostController::class, 'trash'])->name('posts.trash');
    Route::get('/trash/eligible-authors', [PostController::class, 'trashEligibleAuthors'])->name('posts.trash-eligible-authors');
    Route::post('/trash/restore/bulk', [PostController::class, 'bulkRestore'])->name('posts.bulk-restore');
    Route::post('/trash/{id}/restore', [PostController::class, 'restore'])->name('posts.restore');
    Route::delete('/trash/bulk', [PostController::class, 'bulkForceDestroy'])->name('posts.bulk-force-destroy');
    Route::delete('/trash/{id}', [PostController::class, 'forceDestroy'])->name('posts.force-destroy');

    // Autosave endpoints (must be before {post:slug} wildcard routes)
    Route::post('/autosave', [PostAutosaveController::class, 'save'])->name('posts.autosave.save');
    Route::get('/autosave', [PostAutosaveController::class, 'retrieve'])->name('posts.autosave.retrieve');
    Route::delete('/autosave', [PostAutosaveController::class, 'discard'])->name('posts.autosave.discard');

    // Wildcard routes (must be last to avoid matching specific routes like /autosave)
    Route::get('/{post:slug}/revisions', [PostController::class, 'revisions'])->name('posts.revisions');
    Route::post('/{post:slug}/revisions/compare', [PostController::class, 'compareRevisions'])->name('posts.compare-revisions');
    Route::get('/{post:slug}/analytics', [PostController::class, 'analytics'])->name('posts.analytics');
    Route::get('/{post:slug}/preview', [PostAutosaveController::class, 'preview'])->name('posts.autosave.preview');
    Route::put('/{post:slug}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/{post:slug}', [PostController::class, 'destroy'])->name('posts.destroy');
});

// Public post routes (must be after authenticated routes to avoid conflicts)
Route::prefix('posts')->group(function () {
    Route::get('/{post:slug}', [PostController::class, 'show'])->name('posts.show');
});

// Tag management endpoints (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('tags')->group(function () {
    Route::get('/', [TagController::class, 'index'])->name('tags.index');
    Route::get('/{slug}', [TagController::class, 'show'])->name('tags.show');
});

// Category management endpoints (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/', [CategoryController::class, 'store'])->name('categories.store');
    Route::delete('/bulk', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
    Route::get('/trash', [CategoryController::class, 'trash'])->name('categories.trash');
    Route::post('/trash/restore/bulk', [CategoryController::class, 'bulkRestore'])->name('categories.bulk-restore');
    Route::post('/trash/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::delete('/trash/bulk', [CategoryController::class, 'bulkForceDestroy'])->name('categories.bulk-force-destroy');
    Route::delete('/trash/{id}', [CategoryController::class, 'forceDestroy'])->name('categories.force-destroy');
    Route::get('/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::put('/{category:slug}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/{category:slug}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

// API Consumer management endpoints (authenticated + verified, admin/master only)
Route::middleware(['auth:sanctum', 'verified'])->prefix('api-consumers')->group(function () {
    Route::get('/', [ApiConsumerController::class, 'index'])->name('api-consumers.index');
    Route::get('/analytics', [ApiConsumerController::class, 'overallAnalytics'])->name('api-consumers.overall-analytics');
    Route::post('/', [ApiConsumerController::class, 'store'])->name('api-consumers.store');
    Route::get('/trash', [ApiConsumerController::class, 'trash'])->name('api-consumers.trash');
    Route::post('/trash/restore/bulk', [ApiConsumerController::class, 'bulkRestore'])->name('api-consumers.bulk-restore');
    Route::post('/trash/{id}/restore', [ApiConsumerController::class, 'restore'])->name('api-consumers.restore');
    Route::delete('/trash/bulk', [ApiConsumerController::class, 'bulkForceDestroy'])->name('api-consumers.bulk-force-destroy');
    Route::delete('/trash/{id}', [ApiConsumerController::class, 'forceDestroy'])->name('api-consumers.force-destroy');
    Route::get('/{apiConsumer}', [ApiConsumerController::class, 'show'])->name('api-consumers.show');
    Route::put('/{apiConsumer}', [ApiConsumerController::class, 'update'])->name('api-consumers.update');
    Route::delete('/{apiConsumer}', [ApiConsumerController::class, 'destroy'])->name('api-consumers.destroy');
    Route::post('/{apiConsumer}/regenerate-key', [ApiConsumerController::class, 'regenerateKey'])->name('api-consumers.regenerate-key');
    Route::post('/{apiConsumer}/toggle-status', [ApiConsumerController::class, 'toggleStatus'])->name('api-consumers.toggle-status');
    Route::get('/{apiConsumer}/statistics', [ApiConsumerController::class, 'statistics'])->name('api-consumers.statistics');
    Route::get('/{apiConsumer}/analytics', [ApiConsumerController::class, 'analytics'])->name('api-consumers.analytics');
});

// Public Blog API endpoints (API key authentication for consumption by multiple websites)
Route::middleware(['api.key'])->prefix('public/blog')->group(function () {
    // Posts endpoints
    Route::get('/posts', [PublicBlogController::class, 'posts'])
        ->middleware(CacheResponse::for(300, 'blog-posts'));
    Route::get('/posts/featured', [PublicBlogController::class, 'featured'])
        ->middleware(CacheResponse::for(300, 'blog-posts'));
    Route::get('/posts/search', [PublicBlogController::class, 'search'])
        ->middleware(CacheResponse::for(120, 'blog-posts'));
    Route::get('/posts/{slug}', [PublicBlogController::class, 'post']); // No cache - has trackVisit

    // Categories endpoints
    Route::get('/categories', [PublicBlogController::class, 'categories'])
        ->middleware(CacheResponse::for(1800, 'blog-categories'));
    Route::get('/categories/{slug}', [PublicBlogController::class, 'category'])
        ->middleware(CacheResponse::for(1800, 'blog-categories'));
    Route::get('/categories/{slug}/posts', [PublicBlogController::class, 'postsByCategory'])
        ->middleware(CacheResponse::for(300, 'blog-posts'));

    // Tags endpoints
    Route::get('/tags/{tag}/posts', [PublicBlogController::class, 'postsByTag'])
        ->middleware(CacheResponse::for(300, 'blog-posts'));

    // Authors endpoints
    Route::get('/authors/{username}/posts', [PublicBlogController::class, 'postsByAuthor'])
        ->middleware(CacheResponse::for(300, 'blog-posts'));
});

// Public Project & Event API endpoints (API key authentication)
Route::middleware(['api.key'])->prefix('public/projects')->group(function () {
    Route::get('/{username}', [PublicProjectController::class, 'show'])
        ->middleware(CacheResponse::for(900, 'projects'));
    Route::get('/{username}/events', [PublicProjectController::class, 'events'])
        ->middleware(CacheResponse::for(900, 'events'));
    Route::get('/{username}/events/active', [PublicProjectController::class, 'activeEvent'])
        ->middleware(CacheResponse::for(900, 'events'));
    Route::get('/{username}/events/{eventSlug}', [PublicProjectController::class, 'event'])
        ->middleware(CacheResponse::for(900, 'events'));
    Route::get('/{username}/events/{eventSlug}/brands', [PublicProjectController::class, 'brands'])
        ->middleware(CacheResponse::for(600, 'brands'));
    Route::get('/{username}/events/{eventSlug}/brands/{brandSlug}', [PublicProjectController::class, 'brand'])
        ->middleware(CacheResponse::for(900, 'brands'));
    Route::get('/{username}/events/{eventSlug}/brands/{brandSlug}/promotion-posts', [PublicProjectController::class, 'promotionPosts'])
        ->middleware(CacheResponse::for(600, 'promotion-posts'));
});

// Public Exchange Rate API endpoints (no authentication required, public proxy)
Route::prefix('exchange-rates')->middleware('throttle:api')->group(function () {
    Route::get('/', [ExchangeRateController::class, 'index'])
        ->middleware(CacheResponse::for(1800, 'exchange-rates'));
    Route::get('/currencies', [ExchangeRateController::class, 'currencies'])
        ->middleware(CacheResponse::for(3600, 'exchange-rates'));
    Route::get('/popular', [ExchangeRateController::class, 'popular'])
        ->middleware(CacheResponse::for(1800, 'exchange-rates'));
    Route::get('/convert', [ExchangeRateController::class, 'convert'])
        ->middleware(CacheResponse::for(1800, 'exchange-rates'));
    Route::get('/{currency}', [ExchangeRateController::class, 'show'])
        ->middleware(CacheResponse::for(1800, 'exchange-rates'));
});
