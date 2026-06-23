<?php

use App\Http\Controllers\Api\AccessCodeController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AllotmentController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AnalyticsSyncLogController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ApiConsumerController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\BrandEventController;
use App\Http\Controllers\Api\ContactBusinessCategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactFormController;
use App\Http\Controllers\Api\ContactFormSubmissionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventAttendeeAnalyticsController;
use App\Http\Controllers\Api\EventAttendeeController;
use App\Http\Controllers\Api\EventConjunctionController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventCustomFieldController;
use App\Http\Controllers\Api\EventDayController;
use App\Http\Controllers\Api\EventDocumentController;
use App\Http\Controllers\Api\EventProductCategoryController;
use App\Http\Controllers\Api\EventProductController;
use App\Http\Controllers\Api\EventTicketSettingsController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExhibitorDashboardController;
use App\Http\Controllers\Api\ExhibitorLeadController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormFieldController;
use App\Http\Controllers\Api\FormResponseController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\GoogleAnalyticsController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\HotelTransferOptionController;
use App\Http\Controllers\Api\ImportProgressController;
use App\Http\Controllers\Api\JobProgressController;
use App\Http\Controllers\Api\LinkPageBannerController;
use App\Http\Controllers\Api\LinkPageController;
use App\Http\Controllers\Api\LinkPageItemController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\MediaCoverageController;
use App\Http\Controllers\Api\MyTicketsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderAdjustmentController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PartnerCategoryController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\Payment\PaymentGatewayBalanceController;
use App\Http\Controllers\Api\Payment\PaymentGatewayReconciliationController;
use App\Http\Controllers\Api\Payment\PaymentGatewaySettlementController;
use App\Http\Controllers\Api\Payment\PaymentGatewayTransactionController;
use App\Http\Controllers\Api\Payment\PaymentGatewayWebhookEventController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PostAutosaveController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProjectActivityController;
use App\Http\Controllers\Api\ProjectBannerController;
use App\Http\Controllers\Api\ProjectBrandingController;
use App\Http\Controllers\Api\ProjectBusinessCategoryController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectCustomFieldController;
use App\Http\Controllers\Api\ProjectPaymentGatewayController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\PromotionRuleController;
use App\Http\Controllers\Api\Public\PublicAccessCodeController;
use App\Http\Controllers\Api\Public\PublicAttendeeController;
use App\Http\Controllers\Api\Public\PublicBannerController;
use App\Http\Controllers\Api\Public\PublicHotelController;
use App\Http\Controllers\Api\Public\PublicPromoCodeController;
use App\Http\Controllers\Api\Public\PublicReservationController;
use App\Http\Controllers\Api\Public\PublicTicketController;
use App\Http\Controllers\Api\Public\PublicTicketOrderController;
use App\Http\Controllers\Api\PublicBlogController;
use App\Http\Controllers\Api\PublicFormController;
use App\Http\Controllers\Api\PublicProjectController;
use App\Http\Controllers\Api\ReservationAdjustmentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ResponseCacheController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RolesPermissionsSyncController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\RundownItemController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\Api\Shaders\ShapeSdfController;
use App\Http\Controllers\Api\SheetsController;
use App\Http\Controllers\Api\ShortLinkController;
use App\Http\Controllers\Api\SyncPermissionsController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TemporaryUploadController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketPricePhaseController;
use App\Http\Controllers\Api\TicketSessionController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserImpersonationController;
use App\Http\Controllers\Api\UserNoteController;
use App\Http\Controllers\Api\UserSecurityController;
use App\Http\Controllers\Api\Webhook\MidtransWebhookController;
use App\Http\Controllers\Api\Webhook\XenditWebhookController;
use App\Http\Controllers\Api\WhatsAppTestController;
use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;
use Spatie\ResponseCache\Middlewares\CacheResponse;

// Basic authenticated routes
Route::middleware(['auth:sanctum'])->get('/user', [UserController::class, 'profile']);

// Leaving impersonation must work for ANY authenticated session (the impersonated
// account may be unverified or lack permissions), so it lives here, not in the
// permission-gated users group.
Route::middleware(['auth:sanctum'])->post('/users/impersonate/leave', [UserImpersonationController::class, 'leave']);

// Public: stream a generated SDF .bin THROUGH Laravel so the CORS middleware applies
// (static /storage files bypass the kernel and get no CORS, which blocks the shader's
// cross-origin fetch). Anyone can read these - they are public shader assets.
Route::get('public/shaders/sdf/{filename}', [ShapeSdfController::class, 'serve'])
    ->where('filename', '[A-Za-z0-9._-]+\\.bin')
    ->name('shaders.sdf.serve');

// Dashboard routes (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/navigation', [DashboardController::class, 'navigation'])->name('dashboard.navigation');
    Route::get('/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/writer-stats', [DashboardController::class, 'writerStats'])->name('dashboard.writer-stats');

    // Dashboard announcements (visible to user) + dismiss
    Route::get('/announcements', [AnnouncementController::class, 'forCurrentUser'])->name('dashboard.announcements');
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('dashboard.announcements.dismiss');
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

    // Shaders SDF converter + stored library (admin/master only - role gate in controller/request)
    Route::prefix('shaders/sdf')->group(function () {
        Route::get('/', [ShapeSdfController::class, 'index'])->name('shaders.sdf.index');
        Route::post('/', [ShapeSdfController::class, 'store'])
            ->middleware('throttle:sdf-convert')
            ->name('shaders.sdf.store');
        Route::delete('/bulk', [ShapeSdfController::class, 'bulkDestroy'])->name('shaders.sdf.bulk-destroy');
        Route::delete('/{filename}', [ShapeSdfController::class, 'destroy'])
            ->where('filename', '[A-Za-z0-9._-]+\\.bin')
            ->name('shaders.sdf.destroy');
    });

    // Media endpoints
    Route::prefix('media')->group(function () {
        Route::post('/upload', [MediaController::class, 'upload']);
        Route::post('/bulk-upload', [MediaController::class, 'bulkUpload']);
        Route::delete('/bulk-delete', [MediaController::class, 'bulkDelete']);
        Route::post('/reorder', [MediaController::class, 'reorder']);
        Route::get('/{media}/download', [MediaController::class, 'download']);
        Route::patch('/{media}', [MediaController::class, 'update']);
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

        // User security & account management (literal segments before /{user})
        Route::get('/stats', [UserSecurityController::class, 'stats']);
        Route::post('/bulk/force-logout', [UserController::class, 'bulkForceLogout'])->middleware('can:users.manage_sessions');
        Route::post('/bulk/send-password-reset', [UserController::class, 'bulkSendPasswordReset'])->middleware('can:users.send_account_emails');

        // Impersonation start: master-only (enforced inside the controller, never a
        // permission), silent. The matching "leave" route lives outside this group so an
        // impersonated (possibly unverified / low-permission) account can always exit.
        Route::post('/{user}/impersonate', [UserImpersonationController::class, 'start']);

        Route::get('/{user}/sessions', [UserSecurityController::class, 'sessions'])->middleware('can:users.manage_sessions');
        Route::delete('/{user}/sessions/{sessionId}', [UserSecurityController::class, 'revokeSession'])->middleware('can:users.manage_sessions');
        Route::delete('/{user}/sessions', [UserSecurityController::class, 'clearAllSessions'])->middleware('can:users.manage_sessions');
        Route::get('/{user}/tokens', [UserSecurityController::class, 'tokens'])->middleware('can:users.manage_sessions');
        Route::delete('/{user}/tokens/{tokenId}', [UserSecurityController::class, 'revokeToken'])->middleware('can:users.manage_sessions');
        Route::get('/{user}/login-history', [UserSecurityController::class, 'loginHistory'])->middleware('can:users.view_security');
        Route::get('/{user}/security', [UserSecurityController::class, 'securityOverview'])->middleware('can:users.view_security');
        Route::post('/{user}/send-password-reset', [UserSecurityController::class, 'sendPasswordReset'])->middleware(['can:users.send_account_emails', 'throttle:6,1']);
        Route::post('/{user}/resend-verification', [UserSecurityController::class, 'resendVerification'])->middleware(['can:users.send_account_emails', 'throttle:6,1']);
        Route::delete('/{user}/two-factor', [UserSecurityController::class, 'resetTwoFactor'])->middleware('can:users.reset_2fa');
        Route::post('/{user}/suspend', [UserSecurityController::class, 'suspend'])->middleware('can:users.suspend');
        Route::post('/{user}/unsuspend', [UserSecurityController::class, 'unsuspend'])->middleware('can:users.suspend');
        Route::get('/{user}/notes', [UserNoteController::class, 'index'])->middleware('can:users.manage_notes');
        Route::post('/{user}/notes', [UserNoteController::class, 'store'])->middleware('can:users.manage_notes');
        Route::delete('/{user}/notes/{note}', [UserNoteController::class, 'destroy'])->middleware('can:users.manage_notes');

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

    // Roles & permissions sync (export/import)
    Route::prefix('roles-permissions')->group(function () {
        Route::get('/export', [RolesPermissionsSyncController::class, 'export']);
        Route::post('/import', [RolesPermissionsSyncController::class, 'import']);
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
        Route::patch('/{username}/website-settings', [ProjectController::class, 'updateWebsiteSettings'])->name('projects.website-settings');
        Route::patch('/{username}/hotel-reservation-toggle', [ProjectController::class, 'toggleHotelReservation'])->name('projects.hotel-reservation-toggle');
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

    // Project banners endpoints (project-level website banners)
    Route::prefix('projects/{project:username}/banners')->group(function () {
        Route::get('/', [ProjectBannerController::class, 'index'])->name('projects.banners.index');
        Route::post('/', [ProjectBannerController::class, 'store'])->name('projects.banners.store');
        Route::post('/reorder', [ProjectBannerController::class, 'reorder'])->name('projects.banners.reorder');
        Route::delete('/bulk-delete', [ProjectBannerController::class, 'bulkDelete'])->name('projects.banners.bulk-delete');
        Route::get('/{banner}/analytics', [ProjectBannerController::class, 'analytics'])->name('projects.banners.analytics');
        Route::put('/{banner}', [ProjectBannerController::class, 'update'])->name('projects.banners.update');
        Route::patch('/{banner}/toggle', [ProjectBannerController::class, 'toggleActive'])->name('projects.banners.toggle');
    });

    // Project business categories endpoints
    Route::prefix('projects/{username}/business-categories')->group(function () {
        Route::get('/', [ProjectBusinessCategoryController::class, 'index'])->name('projects.business-categories.index');
        Route::post('/', [ProjectBusinessCategoryController::class, 'store'])->name('projects.business-categories.store');
        Route::get('/export', [ProjectBusinessCategoryController::class, 'export'])->name('projects.business-categories.export');
        Route::get('/import/template', [ProjectBusinessCategoryController::class, 'downloadTemplate'])->name('projects.business-categories.import.template');
        Route::post('/import', [ProjectBusinessCategoryController::class, 'import'])->name('projects.business-categories.import');
        Route::put('/reorder', [ProjectBusinessCategoryController::class, 'reorder'])->name('projects.business-categories.reorder');
        Route::put('/{id}', [ProjectBusinessCategoryController::class, 'update'])->name('projects.business-categories.update');
        Route::delete('/{id}', [ProjectBusinessCategoryController::class, 'destroy'])->name('projects.business-categories.destroy');
    });

    // Events (cross-project listing)
    Route::get('/events', [EventController::class, 'all'])->name('events.all');

    // Events trash (global cross-project)
    Route::get('/events-trash', [EventController::class, 'allTrash'])->middleware('can:events.delete')->name('events.all-trash');
    Route::post('/events-trash/restore/bulk', [EventController::class, 'bulkRestore'])->middleware('can:events.delete')->name('events.bulk-restore');
    Route::post('/events-trash/{id}/restore', [EventController::class, 'restoreById'])->middleware('can:events.delete')->name('events.restore-by-id');
    Route::delete('/events-trash/bulk', [EventController::class, 'bulkForceDestroy'])->middleware('can:events.delete')->name('events.bulk-force-delete');
    Route::delete('/events-trash/{id}', [EventController::class, 'forceDestroyById'])->middleware('can:events.delete')->name('events.force-destroy-by-id');

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

    // Guest management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/guests')->group(function () {
        Route::get('/', [GuestController::class, 'index'])->name('guests.index');
        Route::post('/', [GuestController::class, 'store'])->name('guests.store');
        Route::post('/reorder', [GuestController::class, 'reorder'])->name('guests.reorder');
        Route::get('/trash', [GuestController::class, 'trash'])->name('guests.trash');
        Route::post('/trash/restore-bulk', [GuestController::class, 'bulkRestore'])->name('guests.bulk-restore');
        Route::delete('/trash/force-bulk', [GuestController::class, 'bulkForceDestroy'])->name('guests.bulk-force-destroy');
        Route::post('/trash/{id}/restore', [GuestController::class, 'restore'])->name('guests.restore');
        Route::delete('/trash/{id}', [GuestController::class, 'forceDestroy'])->name('guests.force-destroy');
        Route::delete('/bulk', [GuestController::class, 'bulkDestroy'])->name('guests.bulk-destroy');
        Route::patch('/bulk', [GuestController::class, 'bulkUpdate'])->name('guests.bulk-update');
        Route::post('/bulk-move', [GuestController::class, 'bulkMove'])->name('guests.bulk-move');
        Route::post('/{id}/duplicate', [GuestController::class, 'duplicate'])->name('guests.duplicate');
        Route::get('/{id}/activities', [GuestController::class, 'activities'])->name('guests.activities');
        Route::get('/{id}', [GuestController::class, 'show'])->name('guests.show');
        Route::put('/{id}', [GuestController::class, 'update'])->name('guests.update');
        Route::delete('/{id}', [GuestController::class, 'destroy'])->name('guests.destroy');
    });

    // Program management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/programs')->group(function () {
        Route::get('/', [ProgramController::class, 'index'])->name('programs.index');
        Route::post('/', [ProgramController::class, 'store'])->name('programs.store');
        Route::post('/reorder', [ProgramController::class, 'reorder'])->name('programs.reorder');
        Route::get('/trash', [ProgramController::class, 'trash'])->name('programs.trash');
        Route::post('/trash/restore-bulk', [ProgramController::class, 'bulkRestore'])->name('programs.bulk-restore');
        Route::delete('/trash/force-bulk', [ProgramController::class, 'bulkForceDestroy'])->name('programs.bulk-force-destroy');
        Route::post('/trash/{id}/restore', [ProgramController::class, 'restore'])->name('programs.restore');
        Route::delete('/trash/{id}', [ProgramController::class, 'forceDestroy'])->name('programs.force-destroy');
        Route::delete('/bulk', [ProgramController::class, 'bulkDestroy'])->name('programs.bulk-destroy');
        Route::patch('/bulk', [ProgramController::class, 'bulkUpdate'])->name('programs.bulk-update');
        Route::get('/{id}', [ProgramController::class, 'show'])->name('programs.show');
        Route::put('/{id}', [ProgramController::class, 'update'])->name('programs.update');
        Route::delete('/{id}', [ProgramController::class, 'destroy'])->name('programs.destroy');
    });

    // FAQ management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/faqs')->group(function () {
        Route::get('/', [FaqController::class, 'index'])->name('faqs.index');
        Route::post('/', [FaqController::class, 'store'])->name('faqs.store');
        Route::post('/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
        Route::get('/source-events', [FaqController::class, 'sourceEvents'])->name('faqs.source-events');
        Route::post('/copy-from-event', [FaqController::class, 'copyFromEvent'])->name('faqs.copy-from-event');
        Route::get('/trash', [FaqController::class, 'trash'])->name('faqs.trash');
        Route::post('/trash/restore-bulk', [FaqController::class, 'bulkRestore'])->name('faqs.bulk-restore');
        Route::delete('/trash/force-bulk', [FaqController::class, 'bulkForceDestroy'])->name('faqs.bulk-force-destroy');
        Route::post('/trash/{id}/restore', [FaqController::class, 'restore'])->name('faqs.restore');
        Route::delete('/trash/{id}', [FaqController::class, 'forceDestroy'])->name('faqs.force-destroy');
        Route::delete('/bulk', [FaqController::class, 'bulkDestroy'])->name('faqs.bulk-destroy');
        Route::patch('/bulk', [FaqController::class, 'bulkUpdate'])->name('faqs.bulk-update');
        Route::get('/{id}', [FaqController::class, 'show'])->name('faqs.show');
        Route::put('/{id}', [FaqController::class, 'update'])->name('faqs.update');
        Route::delete('/{id}', [FaqController::class, 'destroy'])->name('faqs.destroy');
    });

    // Media Coverage management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/media-coverages')->group(function () {
        Route::get('/', [MediaCoverageController::class, 'index'])->name('media-coverages.index');
        Route::post('/', [MediaCoverageController::class, 'store'])->name('media-coverages.store');
        Route::post('/reorder', [MediaCoverageController::class, 'reorder'])->name('media-coverages.reorder');
        Route::get('/source-events', [MediaCoverageController::class, 'sourceEvents'])->name('media-coverages.source-events');
        Route::post('/copy-from-event', [MediaCoverageController::class, 'copyFromEvent'])->name('media-coverages.copy-from-event');
        Route::get('/trash', [MediaCoverageController::class, 'trash'])->name('media-coverages.trash');
        Route::post('/trash/restore-bulk', [MediaCoverageController::class, 'bulkRestore'])->name('media-coverages.bulk-restore');
        Route::delete('/trash/force-bulk', [MediaCoverageController::class, 'bulkForceDestroy'])->name('media-coverages.bulk-force-destroy');
        Route::post('/trash/{id}/restore', [MediaCoverageController::class, 'restore'])->name('media-coverages.restore');
        Route::delete('/trash/{id}', [MediaCoverageController::class, 'forceDestroy'])->name('media-coverages.force-destroy');
        Route::delete('/bulk', [MediaCoverageController::class, 'bulkDestroy'])->name('media-coverages.bulk-destroy');
        Route::patch('/bulk', [MediaCoverageController::class, 'bulkUpdate'])->name('media-coverages.bulk-update');
        Route::get('/{id}', [MediaCoverageController::class, 'show'])->name('media-coverages.show');
        Route::put('/{id}', [MediaCoverageController::class, 'update'])->name('media-coverages.update');
        Route::delete('/{id}', [MediaCoverageController::class, 'destroy'])->name('media-coverages.destroy');
    });

    // Gallery management endpoints (nested under events). Reorder + bulk-delete
    // use the generic /api/media/* endpoints (GalleryManager defaults).
    Route::prefix('projects/{username}/events/{eventSlug}/gallery')->group(function () {
        Route::get('/', [GalleryController::class, 'index'])->name('gallery.index');
        Route::post('/', [GalleryController::class, 'store'])->name('gallery.store');
        Route::post('/bulk-delete', [GalleryController::class, 'bulkDelete'])->name('gallery.bulk-delete');
        Route::patch('/settings', [GalleryController::class, 'updateSettings'])->name('gallery.settings.update');
    });

    // Rundown item management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/rundown-items')->group(function () {
        Route::get('/', [RundownItemController::class, 'index'])->name('rundown-items.index');
        Route::post('/', [RundownItemController::class, 'store'])->name('rundown-items.store');
        Route::post('/reorder', [RundownItemController::class, 'reorder'])->name('rundown-items.reorder');
        Route::get('/export', [RundownItemController::class, 'export'])->name('rundown-items.export');
        Route::get('/export/json', [RundownItemController::class, 'exportJson'])->name('rundown-items.export.json');
        Route::get('/import/template', [RundownItemController::class, 'downloadTemplate'])->name('rundown-items.import.template');
        Route::post('/import', [RundownItemController::class, 'import'])->name('rundown-items.import');
        Route::post('/import/json', [RundownItemController::class, 'importJson'])->name('rundown-items.import.json');
        Route::get('/trash', [RundownItemController::class, 'trash'])->name('rundown-items.trash');
        Route::post('/trash/{id}/restore', [RundownItemController::class, 'restore'])->name('rundown-items.restore');
        Route::delete('/trash/{id}', [RundownItemController::class, 'forceDestroy'])->name('rundown-items.force-destroy');
        Route::get('/{id}', [RundownItemController::class, 'show'])->name('rundown-items.show');
        Route::put('/{id}', [RundownItemController::class, 'update'])->name('rundown-items.update');
        Route::delete('/{id}', [RundownItemController::class, 'destroy'])->name('rundown-items.destroy');
    });

    // Event product category management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/product-categories')->group(function () {
        Route::get('/', [EventProductCategoryController::class, 'index'])->name('event-product-categories.index');
        Route::post('/', [EventProductCategoryController::class, 'store'])->name('event-product-categories.store');
        Route::post('/reorder', [EventProductCategoryController::class, 'reorder'])->name('event-product-categories.reorder');
        Route::get('/{id}', [EventProductCategoryController::class, 'show'])->name('event-product-categories.show');
        Route::put('/{id}', [EventProductCategoryController::class, 'update'])->name('event-product-categories.update');
        Route::delete('/{id}', [EventProductCategoryController::class, 'destroy'])->name('event-product-categories.destroy');
    });

    // Event document management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/documents')->group(function () {
        Route::get('/', [EventDocumentController::class, 'index'])->name('event-documents.index');
        Route::post('/', [EventDocumentController::class, 'store'])->name('event-documents.store');
        Route::post('/reorder', [EventDocumentController::class, 'reorder'])->name('event-documents.reorder');
        Route::get('/{ulid}', [EventDocumentController::class, 'show'])->name('event-documents.show');
        Route::put('/{ulid}', [EventDocumentController::class, 'update'])->name('event-documents.update');
        Route::delete('/{ulid}', [EventDocumentController::class, 'destroy'])->name('event-documents.destroy');
    });

    // Order management endpoints (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/orders')->group(function () {
        Route::get('/export', [OrderController::class, 'export'])->name('orders.export');
        Route::get('/', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/{ulid}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('/{ulid}/operational-status', [OrderController::class, 'updateOperationalStatus'])->name('orders.update-operational-status');
        Route::patch('/{ulid}/payment-status', [OrderController::class, 'updatePaymentStatus'])->name('orders.update-payment-status');
        Route::delete('/{ulid}', [OrderController::class, 'destroy'])->name('orders.destroy');

        Route::post('/{ulid}/adjustments', [OrderAdjustmentController::class, 'store'])
            ->name('orders.adjustments.store');
        Route::delete('/{ulid}/adjustments/{adjustment:ulid}', [OrderAdjustmentController::class, 'destroy'])
            ->name('orders.adjustments.destroy');

        Route::patch('/{ulid}/internal-notes', [OrderController::class, 'updateInternalNotes'])
            ->name('orders.update-internal-notes');

        Route::post('/{ulid}/invoice', [OrderController::class, 'uploadInvoice'])->name('orders.upload-invoice');
        Route::post('/{ulid}/receipt', [OrderController::class, 'uploadReceipt'])->name('orders.upload-receipt');
        Route::post('/{ulid}/send-invoice', [OrderController::class, 'sendInvoice'])->name('orders.send-invoice');
        Route::post('/{ulid}/send-receipt', [OrderController::class, 'sendReceipt'])->name('orders.send-receipt');
    });

    // Promotion rule & promo code admin CRUD
    Route::prefix('promotion-rules')->group(function () {
        Route::get('/', [PromotionRuleController::class, 'index'])->name('promotion-rules.index');
        Route::get('/trash', [PromotionRuleController::class, 'trash'])->name('promotion-rules.trash');
        Route::get('/export', [PromotionRuleController::class, 'export'])->name('promotion-rules.export');
        Route::delete('/bulk', [PromotionRuleController::class, 'bulkDestroy'])->name('promotion-rules.bulk-destroy');
        Route::post('/', [PromotionRuleController::class, 'store'])->name('promotion-rules.store');
        Route::get('/{rule:ulid}', [PromotionRuleController::class, 'show'])->name('promotion-rules.show');
        Route::patch('/{rule:ulid}', [PromotionRuleController::class, 'update'])->name('promotion-rules.update');
        Route::delete('/{rule:ulid}', [PromotionRuleController::class, 'destroy'])->name('promotion-rules.destroy');
        Route::post('/{ulid}/restore', [PromotionRuleController::class, 'restore'])->name('promotion-rules.restore');
        Route::get('/{rule:ulid}/report', [PromotionRuleController::class, 'report'])->name('promotion-rules.report');
        Route::post('/{rule:ulid}/codes', [PromoCodeController::class, 'store'])->name('promo-codes.store');
        Route::post('/{rule:ulid}/codes/bulk', [PromoCodeController::class, 'bulkStore'])->name('promo-codes.bulk-store');
    });

    Route::prefix('promo-codes')->group(function () {
        Route::get('/', [PromoCodeController::class, 'index'])->name('promo-codes.index');
        Route::get('/export', [PromoCodeController::class, 'export'])->name('promo-codes.export');
        Route::delete('/bulk', [PromoCodeController::class, 'bulkDestroy'])->name('promo-codes.bulk-destroy');
        Route::get('/trash', [PromoCodeController::class, 'trash'])->name('promo-codes.trash');
        Route::get('/{code:ulid}', [PromoCodeController::class, 'show'])->name('promo-codes.show');
        Route::patch('/{code:ulid}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
        Route::delete('/{code:ulid}', [PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');
        Route::get('/{code:ulid}/usages', [PromoCodeController::class, 'usages'])->name('promo-codes.usages');
        Route::post('/{ulid}/restore', [PromoCodeController::class, 'restore'])->name('promo-codes.restore');
    });

    // Brand management endpoints (nested under project events)
    Route::prefix('projects/{username}/events/{eventSlug}/brands')->group(function () {
        Route::get('/', [BrandEventController::class, 'index'])->name('brand-events.index');
        Route::post('/', [BrandEventController::class, 'store'])->name('brand-events.store');
        Route::get('/export', [BrandEventController::class, 'export'])->name('brand-events.export');
        Route::get('/import/template', [BrandEventController::class, 'downloadTemplate'])->name('brand-events.import.template');
        Route::post('/import', [BrandEventController::class, 'import'])->name('brand-events.import');
        Route::delete('/bulk', [BrandEventController::class, 'bulkDestroy'])->name('brand-events.bulk-destroy');
        Route::delete('/bulk-permanent', [BrandEventController::class, 'bulkPermanentDelete'])->name('brand-events.bulk-permanent-delete');
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
        Route::get('/{brandSlug}/document-submissions', [BrandEventController::class, 'documentSubmissions'])->name('brand-events.document-submissions.index');

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
    Route::delete('/brands/bulk', [BrandController::class, 'bulkDestroy'])->middleware('can:brands.delete')->name('brands.bulk-destroy');
    Route::delete('/brands/{brand}', [BrandController::class, 'destroy'])->middleware('can:brands.delete')->name('brands.delete');
    Route::get('/brands/{brand}/members', [BrandController::class, 'members'])->middleware('can:brands.read')->name('brands.members.index');
    Route::post('/brands/{brand}/members', [BrandController::class, 'addMember'])->middleware('can:brands.update')->name('brands.members.store');
    Route::delete('/brands/{brand}/members/{userId}', [BrandController::class, 'removeMember'])->middleware('can:brands.update')->name('brands.members.destroy');

    // Brand trash routes
    Route::get('/brands-trash', [BrandController::class, 'trash'])->middleware('can:brands.delete')->name('brands.trash');
    Route::post('/brands-trash/restore/bulk', [BrandController::class, 'bulkRestore'])->middleware('can:brands.delete')->name('brands.bulk-restore');
    Route::post('/brands-trash/{id}/restore', [BrandController::class, 'restore'])->middleware('can:brands.delete')->name('brands.restore');
    Route::delete('/brands-trash/bulk', [BrandController::class, 'bulkForceDestroy'])->middleware('can:brands.delete')->name('brands.bulk-force-delete');
    Route::delete('/brands-trash/{id}', [BrandController::class, 'forceDestroy'])->middleware('can:brands.delete')->name('brands.force-delete');

    // Partner category management (nested under project events)
    Route::prefix('projects/{username}/events/{eventSlug}/partner-categories')->group(function () {
        Route::get('/', [PartnerCategoryController::class, 'index'])->name('partner-categories.index');
        Route::post('/', [PartnerCategoryController::class, 'store'])->name('partner-categories.store');
        Route::post('/copy-from-event', [PartnerCategoryController::class, 'copyFromEvent'])->name('partner-categories.copy-from-event');
        Route::post('/update-order', [PartnerCategoryController::class, 'updateOrder'])->name('partner-categories.update-order');
        Route::put('/{categorySlug}', [PartnerCategoryController::class, 'update'])->name('partner-categories.update');
        Route::delete('/{categorySlug}', [PartnerCategoryController::class, 'destroy'])->name('partner-categories.destroy');
        Route::post('/{categorySlug}/partners', [PartnerCategoryController::class, 'addPartner'])->name('partner-categories.add-partner');
        Route::delete('/{categorySlug}/partners/{pivotId}', [PartnerCategoryController::class, 'removePartner'])->name('partner-categories.remove-partner');
        Route::post('/{categorySlug}/partners/update-order', [PartnerCategoryController::class, 'updatePartnerOrder'])->name('partner-categories.update-partner-order');
    });

    // Event conjunction management (nested under events)
    Route::prefix('projects/{username}/events/{eventSlug}/conjunctions')->group(function () {
        Route::get('/', [EventConjunctionController::class, 'index'])->name('event-conjunctions.index');
        Route::get('/available', [EventConjunctionController::class, 'available'])->name('event-conjunctions.available');
        Route::post('/', [EventConjunctionController::class, 'store'])->name('event-conjunctions.store');
        Route::post('/reorder', [EventConjunctionController::class, 'reorder'])->name('event-conjunctions.reorder');
        Route::put('/cross-scan', [EventConjunctionController::class, 'setCrossScan'])->name('event-conjunctions.cross-scan');
        Route::delete('/{conjunctionEventId}', [EventConjunctionController::class, 'destroy'])->name('event-conjunctions.destroy');
    });

    // Events with partners (for copy-from-event dialog)
    Route::get('/events-with-partners', [PartnerCategoryController::class, 'eventsWithPartners'])->name('events-with-partners');

    // Global partner routes (staff+)
    Route::get('/partners', [PartnerController::class, 'index'])->middleware('can:partners.read')->name('partners.list');
    Route::post('/partners', [PartnerController::class, 'store'])->middleware('can:partners.create')->name('partners.store');
    Route::get('/partners/export', [PartnerController::class, 'export'])->middleware('can:partners.read')->name('partners.export');
    Route::get('/partners/import/template', [PartnerController::class, 'downloadTemplate'])->middleware('can:partners.create')->name('partners.import.template');
    Route::post('/partners/import', [PartnerController::class, 'import'])->middleware('can:partners.create')->name('partners.import');
    Route::get('/partners/search', [PartnerController::class, 'search'])->name('partners.search');
    Route::get('/partners/{partner}', [PartnerController::class, 'show'])->middleware('can:partners.read')->name('partners.show');
    Route::put('/partners/{partner}', [PartnerController::class, 'update'])->middleware('can:partners.update')->name('partners.update');
    Route::delete('/partners/bulk', [PartnerController::class, 'bulkDestroy'])->middleware('can:partners.delete')->name('partners.bulk-destroy');
    Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->middleware('can:partners.delete')->name('partners.delete');

    // Partner trash routes
    Route::get('/partners-trash', [PartnerController::class, 'trash'])->middleware('can:partners.delete')->name('partners.trash');
    Route::post('/partners-trash/restore/bulk', [PartnerController::class, 'bulkRestore'])->middleware('can:partners.delete')->name('partners.bulk-restore');
    Route::post('/partners-trash/{id}/restore', [PartnerController::class, 'restore'])->middleware('can:partners.delete')->name('partners.restore');
    Route::delete('/partners-trash/bulk', [PartnerController::class, 'bulkForceDestroy'])->middleware('can:partners.delete')->name('partners.bulk-force-delete');
    Route::delete('/partners-trash/{id}', [PartnerController::class, 'forceDestroy'])->middleware('can:partners.delete')->name('partners.force-delete');

    // Job progress tracking
    Route::get('/jobs/{jobId}/progress', [JobProgressController::class, 'show'])->name('jobs.progress');
    Route::get('/jobs/{jobId}/download', [JobProgressController::class, 'download'])->middleware('can:contacts.read')->name('jobs.download');

    // Contact management routes
    Route::get('/contacts', [ContactController::class, 'index'])->middleware('can:contacts.read')->name('contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->middleware('can:contacts.create')->name('contacts.store');
    Route::get('/contacts/export', [ContactController::class, 'export'])->middleware('can:contacts.read')->name('contacts.export');
    Route::get('/contacts/import/template', [ContactController::class, 'downloadTemplate'])->middleware('can:contacts.create')->name('contacts.import.template');
    Route::post('/contacts/import', [ContactController::class, 'import'])->middleware('can:contacts.create')->name('contacts.import');
    Route::get('/imports/{importId}/progress', [ImportProgressController::class, 'show'])->name('imports.progress');
    Route::get('/contacts/filter-options', [ContactController::class, 'filterOptions'])->middleware('can:contacts.read')->name('contacts.filter-options');
    Route::get('/contacts/search', [ContactController::class, 'search'])->name('contacts.search');
    Route::get('/contacts/duplicates/scan', [ContactController::class, 'scanDuplicates'])->middleware('can:contacts.delete')->name('contacts.duplicates.scan');
    Route::post('/contacts/duplicates/remove', [ContactController::class, 'removeDuplicates'])->middleware('can:contacts.delete')->name('contacts.duplicates.remove');
    Route::get('/contacts/unused-tags/scan', [ContactController::class, 'scanUnusedTags'])->middleware('can:contacts.delete')->name('contacts.unused-tags.scan');
    Route::post('/contacts/unused-tags/remove', [ContactController::class, 'removeUnusedTags'])->middleware('can:contacts.delete')->name('contacts.unused-tags.remove');
    Route::delete('/contacts/bulk', [ContactController::class, 'bulkDestroy'])->middleware('can:contacts.delete')->name('contacts.bulk-delete');
    Route::delete('/contacts/delete-all', [ContactController::class, 'deleteAll'])->middleware('can:contacts.delete')->name('contacts.delete-all');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->middleware('can:contacts.read')->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('can:contacts.update')->name('contacts.update');
    Route::patch('/contacts/{contact}/status', [ContactController::class, 'updateStatus'])->middleware('can:contacts.update')->name('contacts.update-status');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('can:contacts.delete')->name('contacts.delete');
    Route::get('/contacts-trash', [ContactController::class, 'trash'])->middleware('can:contacts.delete')->name('contacts.trash');
    Route::post('/contacts-trash/restore/bulk', [ContactController::class, 'bulkRestore'])->middleware('can:contacts.delete')->name('contacts.bulk-restore');
    Route::post('/contacts-trash/{id}/restore', [ContactController::class, 'restore'])->middleware('can:contacts.delete')->name('contacts.restore');
    Route::delete('/contacts-trash/bulk', [ContactController::class, 'bulkForceDestroy'])->middleware('can:contacts.delete')->name('contacts.bulk-force-delete');
    Route::delete('/contacts-trash/empty', [ContactController::class, 'emptyTrash'])->middleware('can:contacts.delete')->name('contacts.empty-trash');
    Route::delete('/contacts-trash/{id}', [ContactController::class, 'forceDestroy'])->middleware('can:contacts.delete')->name('contacts.force-delete');

    // Contact business categories
    Route::get('/contacts-business-categories', [ContactBusinessCategoryController::class, 'index'])->middleware('can:contacts.read')->name('contacts.business-categories.index');
    Route::post('/contacts-business-categories', [ContactBusinessCategoryController::class, 'store'])->middleware('can:contacts.create')->name('contacts.business-categories.store');
    Route::put('/contacts-business-categories/reorder', [ContactBusinessCategoryController::class, 'reorder'])->middleware('can:contacts.update')->name('contacts.business-categories.reorder');
    Route::put('/contacts-business-categories/{id}', [ContactBusinessCategoryController::class, 'update'])->middleware('can:contacts.update')->name('contacts.business-categories.update');
    Route::delete('/contacts-business-categories/{id}', [ContactBusinessCategoryController::class, 'destroy'])->middleware('can:contacts.delete')->name('contacts.business-categories.destroy');
    Route::get('/contacts-business-categories/export', [ContactBusinessCategoryController::class, 'export'])->middleware('can:contacts.read')->name('contacts.business-categories.export');
    Route::get('/contacts-business-categories/import/template', [ContactBusinessCategoryController::class, 'downloadTemplate'])->middleware('can:contacts.read')->name('contacts.business-categories.import-template');
    Route::post('/contacts-business-categories/import', [ContactBusinessCategoryController::class, 'import'])->middleware('can:contacts.create')->name('contacts.business-categories.import');

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
        Route::get('/events', [ExhibitorDashboardController::class, 'myEvents'])->name('exhibitor.events');
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

        // Booth fields (fascia_name, badge_name)
        Route::put('/brands/{brandSlug}/events/{brandEventId}/booth-fields', [ExhibitorDashboardController::class, 'updateBoothFields'])->name('exhibitor.booth-fields.update');

        // Event documents & submissions
        Route::get('/brands/{brandSlug}/events/{brandEventId}/documents', [ExhibitorDashboardController::class, 'eventDocuments'])->name('exhibitor.documents.index');
        Route::post('/brands/{brandSlug}/events/{brandEventId}/documents/{documentUlid}', [ExhibitorDashboardController::class, 'submitDocument'])->name('exhibitor.documents.submit');

        // Order period info
        Route::get('/brands/{brandSlug}/events/{brandEventId}/order-period', [ExhibitorDashboardController::class, 'orderPeriodInfo'])->name('exhibitor.order-period');

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

    // Form builder endpoints
    Route::get('/form-templates', [FormController::class, 'templates'])->name('forms.templates');

    Route::prefix('forms')->group(function () {
        Route::get('/', [FormController::class, 'index'])->name('forms.index');
        Route::post('/', [FormController::class, 'store'])->name('forms.store');
        Route::get('/trash', [FormController::class, 'trash'])->name('forms.trash');
        Route::post('/trash/{id}/restore', [FormController::class, 'restore'])->name('forms.restore');
        Route::delete('/trash/{id}', [FormController::class, 'forceDestroy'])->name('forms.force-destroy');
        Route::get('/{form:slug}', [FormController::class, 'show'])->name('forms.show');
        Route::put('/{form:slug}', [FormController::class, 'update'])->name('forms.update');
        Route::delete('/{form:slug}', [FormController::class, 'destroy'])->name('forms.destroy');
        Route::get('/{form:slug}/analytics', [FormController::class, 'analytics'])->name('forms.analytics');
        Route::post('/{form:slug}/duplicate', [FormController::class, 'duplicate'])->name('forms.duplicate');

        // Fields (nested)
        Route::get('/{form:slug}/fields', [FormFieldController::class, 'index'])->name('forms.fields.index');
        Route::post('/{form:slug}/fields', [FormFieldController::class, 'store'])->name('forms.fields.store');
        Route::put('/{form:slug}/fields/reorder', [FormFieldController::class, 'reorder'])->name('forms.fields.reorder');
        Route::put('/{form:slug}/fields/{ulid}', [FormFieldController::class, 'update'])->name('forms.fields.update');
        Route::delete('/{form:slug}/fields/{ulid}', [FormFieldController::class, 'destroy'])->name('forms.fields.destroy');

        // Responses (nested)
        Route::get('/{form:slug}/responses', [FormResponseController::class, 'index'])->name('forms.responses.index');
        Route::get('/{form:slug}/responses/export', [FormResponseController::class, 'export'])->name('forms.responses.export');
        Route::put('/{form:slug}/responses/bulk-status', [FormResponseController::class, 'bulkUpdateStatus'])->name('forms.responses.bulk-status');
        Route::delete('/{form:slug}/responses/bulk', [FormResponseController::class, 'bulkDestroy'])->name('forms.responses.bulk-destroy');
        Route::delete('/{form:slug}/responses/{ulid}', [FormResponseController::class, 'destroy'])->name('forms.responses.destroy');
        Route::get('/{form:slug}/responses/{ulid}/files/{fieldUlid}', [FormResponseController::class, 'downloadFile'])->name('forms.responses.file');
    });

    // Log management endpoints (permission-based)
    Route::prefix('logs')->group(function () {
        Route::get('/', [LogController::class, 'index'])->middleware('can:admin.logs');
        Route::get('/filter-options', [LogController::class, 'filterOptions'])->middleware('can:admin.logs');
        Route::delete('/clear', [LogController::class, 'clear'])->middleware('can:admin.logs_clear');
    });

    // Short link management endpoints
    Route::prefix('short-links')->group(function () {
        Route::get('/', [ShortLinkController::class, 'index'])->name('short-links.index');
        Route::post('/', [ShortLinkController::class, 'store'])->name('short-links.store');
        Route::get('/check-slug', [ShortLinkController::class, 'checkSlug'])->name('short-links.check-slug');
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

    // Link page management endpoints
    Route::prefix('link-pages')->group(function () {
        Route::get('/', [LinkPageController::class, 'index'])->name('link-pages.index');
        Route::post('/', [LinkPageController::class, 'store'])->name('link-pages.store');
        Route::get('/check-slug', [LinkPageController::class, 'checkSlug'])->name('link-pages.check-slug');
        Route::put('/update-order', [LinkPageController::class, 'updateOrder'])->name('link-pages.update-order');
        Route::delete('/bulk', [LinkPageController::class, 'bulkDestroy'])->name('link-pages.bulk-destroy');
        Route::get('/trash', [LinkPageController::class, 'trash'])->name('link-pages.trash');
        Route::post('/trash/restore/bulk', [LinkPageController::class, 'bulkRestore'])->name('link-pages.bulk-restore');
        Route::post('/trash/{id}/restore', [LinkPageController::class, 'restore'])->name('link-pages.restore');
        Route::delete('/trash/bulk', [LinkPageController::class, 'bulkForceDestroy'])->name('link-pages.bulk-force-destroy');
        Route::delete('/trash/{id}', [LinkPageController::class, 'forceDestroy'])->name('link-pages.force-destroy');
        Route::get('/{linkPage:slug}', [LinkPageController::class, 'show'])->name('link-pages.show');
        Route::put('/{linkPage:slug}', [LinkPageController::class, 'update'])->name('link-pages.update');
        Route::delete('/{linkPage:slug}', [LinkPageController::class, 'destroy'])->name('link-pages.destroy');
        Route::get('/{linkPage:slug}/analytics', [LinkPageController::class, 'getAnalytics'])->name('link-pages.analytics');

        // Items (nested)
        Route::get('/{linkPage:slug}/items', [LinkPageItemController::class, 'index'])->name('link-pages.items.index');
        Route::post('/{linkPage:slug}/items', [LinkPageItemController::class, 'store'])->name('link-pages.items.store');
        Route::put('/{linkPage:slug}/items/reorder', [LinkPageItemController::class, 'reorder'])->name('link-pages.items.reorder');
        Route::put('/{linkPage:slug}/items/{linkPageItem}', [LinkPageItemController::class, 'update'])->name('link-pages.items.update');
        Route::delete('/{linkPage:slug}/items/{linkPageItem}', [LinkPageItemController::class, 'destroy'])->name('link-pages.items.destroy');
        Route::patch('/{linkPage:slug}/items/{linkPageItem}/toggle', [LinkPageItemController::class, 'toggleActive'])->name('link-pages.items.toggle');
        Route::get('/{linkPage:slug}/items/trash', [LinkPageItemController::class, 'trash'])->name('link-pages.items.trash');
        Route::post('/{linkPage:slug}/items/trash/{id}/restore', [LinkPageItemController::class, 'restore'])->name('link-pages.items.restore');
        Route::delete('/{linkPage:slug}/items/trash/{id}', [LinkPageItemController::class, 'forceDestroy'])->name('link-pages.items.force-destroy');

        // Banners (nested)
        Route::get('/{linkPage:slug}/banners', [LinkPageBannerController::class, 'index'])->name('link-pages.banners.index');
        Route::post('/{linkPage:slug}/banners', [LinkPageBannerController::class, 'store'])->name('link-pages.banners.store');
        Route::post('/{linkPage:slug}/banners/reorder', [LinkPageBannerController::class, 'reorder'])->name('link-pages.banners.reorder');
        Route::delete('/{linkPage:slug}/banners/bulk-delete', [LinkPageBannerController::class, 'bulkDelete'])->name('link-pages.banners.bulk-delete');
        Route::put('/{linkPage:slug}/banners/{linkPageBanner}', [LinkPageBannerController::class, 'update'])->name('link-pages.banners.update');
        Route::patch('/{linkPage:slug}/banners/{linkPageBanner}/toggle', [LinkPageBannerController::class, 'toggleActive'])->name('link-pages.banners.toggle');
    });

    // AI Chat endpoints
    Route::prefix('ai')->group(function () {
        Route::get('/conversations', [AiChatController::class, 'conversations'])->name('ai.conversations');
        Route::post('/chat', [AiChatController::class, 'chat'])->name('ai.chat');
        Route::get('/usage', [AiChatController::class, 'usage'])->name('ai.usage');
        Route::get('/conversations/{id}/messages', [AiChatController::class, 'messages'])->name('ai.messages');
        Route::delete('/conversations/{id}', [AiChatController::class, 'destroy'])->name('ai.destroy');
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
    ->middleware(['throttle:short-link', CacheResponse::for(3600, 'short-links')]);

// Short link resolution (public - backward compatibility)
Route::get('/s/{slug}', [ProfileController::class, 'resolveShortLink'])
    ->middleware('throttle:short-link');

// Tracking routes (public - can track anonymous visitors)
Route::post('/track/click', [TrackingController::class, 'trackLinkClick'])->middleware('throttle:api');
Route::post('/track/visit', [TrackingController::class, 'trackProfileVisit'])->middleware('throttle:api');

// Public form endpoints (no auth, rate limited)
Route::prefix('public/forms')->middleware('throttle:api')->group(function () {
    Route::get('/{slug}', [PublicFormController::class, 'show'])
        ->middleware(CacheResponse::for(120, 'forms-public'))
        ->name('public.forms.show');
    Route::post('/{slug}/submit', [PublicFormController::class, 'submit'])
        ->middleware('throttle:form-submit')
        ->name('public.forms.submit');
    Route::post('/{slug}/upload', [PublicFormController::class, 'upload'])
        ->middleware('throttle:form-upload')
        ->name('public.forms.upload');
    Route::delete('/{slug}/upload', [PublicFormController::class, 'revert'])
        ->middleware('throttle:form-upload')
        ->name('public.forms.upload.revert');
    Route::get('/{slug}/check', [PublicFormController::class, 'checkDuplicate'])
        ->middleware('throttle:form-upload')
        ->name('public.forms.check');
});

// Google Sheets integration (token-based auth)
Route::get('/sheets/orders/{eventId}', [SheetsController::class, 'orders'])
    ->middleware('throttle:60,1')
    ->name('sheets.orders');
Route::get('/sheets/contacts', [SheetsController::class, 'contacts'])
    ->middleware('throttle:60,1')
    ->name('sheets.contacts');
Route::get('/sheets/brands', [SheetsController::class, 'brands'])
    ->middleware('throttle:60,1')
    ->name('sheets.brands');
Route::get('/sheets/brand-events', [SheetsController::class, 'brandEvents'])
    ->middleware('throttle:60,1')
    ->name('sheets.brand-events');

// Contact form submission (per-IP throttle first to reject floods cheaply, then API key for CORS)
Route::post('/contact-forms/submit', [ContactFormController::class, 'submit'])
    ->middleware(['throttle:contact-submit', 'api.key']);

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
    Route::get('/ga-properties/export', [GoogleAnalyticsController::class, 'export']);
    Route::get('/ga-properties/import/template', [GoogleAnalyticsController::class, 'downloadTemplate']);
    Route::post('/ga-properties/import', [GoogleAnalyticsController::class, 'import']);

    // CRUD endpoints for GA properties
    Route::get('/ga-properties', [GoogleAnalyticsController::class, 'index']);
    Route::post('/ga-properties', [GoogleAnalyticsController::class, 'store']);
    Route::get('/ga-properties/{id}', [GoogleAnalyticsController::class, 'show']);
    Route::put('/ga-properties/{id}', [GoogleAnalyticsController::class, 'update']);
    Route::delete('/ga-properties/{id}', [GoogleAnalyticsController::class, 'destroy']);

    // Analytics data endpoints
    Route::get('/properties', [GoogleAnalyticsController::class, 'getProperties']);
    Route::get('/properties/{id}/analytics', [GoogleAnalyticsController::class, 'getPropertyAnalytics']);
    Route::get('/properties/{id}/analytics/export', [GoogleAnalyticsController::class, 'exportPropertyAnalytics']);
    Route::get('/aggregate', [GoogleAnalyticsController::class, 'getAggregatedAnalytics']);
    Route::get('/aggregate/export', [GoogleAnalyticsController::class, 'exportAggregatedAnalytics']);
    Route::get('/realtime', [GoogleAnalyticsController::class, 'getRealtimeActiveUsers']);
    Route::post('/sync', [GoogleAnalyticsController::class, 'sync']);
    Route::post('/aggregate', [GoogleAnalyticsController::class, 'aggregate']);
    Route::post('/aggregate/sync-now', [GoogleAnalyticsController::class, 'triggerAggregateSyncNow']);
    Route::get('/cache/status', [GoogleAnalyticsController::class, 'getCacheStatus']);
    Route::delete('/cache/properties/{id}', [GoogleAnalyticsController::class, 'clearPropertyCache']);
    Route::delete('/cache/all', [GoogleAnalyticsController::class, 'clearAllCache']);
    Route::delete('/rate-limit', [GoogleAnalyticsController::class, 'clearRateLimit']);

    // Sync logs endpoints
    Route::get('/sync-logs', [AnalyticsSyncLogController::class, 'index']);
    Route::get('/sync-logs/stats', [AnalyticsSyncLogController::class, 'stats']);
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

// Announcement management endpoints (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('announcements')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index'])->middleware('can:announcements.read')->name('announcements.index');
    Route::post('/', [AnnouncementController::class, 'store'])->middleware('can:announcements.create')->name('announcements.store');
    Route::delete('/bulk', [AnnouncementController::class, 'bulkDestroy'])->middleware('can:announcements.delete')->name('announcements.bulk-destroy');
    Route::get('/trash', [AnnouncementController::class, 'trash'])->middleware('can:announcements.delete')->name('announcements.trash');
    Route::post('/trash/restore/bulk', [AnnouncementController::class, 'bulkRestore'])->middleware('can:announcements.delete')->name('announcements.bulk-restore');
    Route::post('/trash/{id}/restore', [AnnouncementController::class, 'restore'])->middleware('can:announcements.delete')->name('announcements.restore');
    Route::delete('/trash/bulk', [AnnouncementController::class, 'bulkForceDestroy'])->middleware('can:announcements.delete')->name('announcements.bulk-force-destroy');
    Route::delete('/trash/{id}', [AnnouncementController::class, 'forceDestroy'])->middleware('can:announcements.delete')->name('announcements.force-destroy');
    Route::get('/{announcement}', [AnnouncementController::class, 'show'])->middleware('can:announcements.read')->name('announcements.show');
    Route::put('/{announcement}', [AnnouncementController::class, 'update'])->middleware('can:announcements.update')->name('announcements.update');
    Route::delete('/{announcement}', [AnnouncementController::class, 'destroy'])->middleware('can:announcements.delete')->name('announcements.destroy');
});

// Tag management endpoints (authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->prefix('tags')->group(function () {
    Route::get('/', [TagController::class, 'index'])->name('tags.index');
    Route::get('/{slug}', [TagController::class, 'show'])->name('tags.show');
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

// Global hotel master CRUD (top-level, hotel as global resource)
Route::middleware(['auth:sanctum', 'verified'])->prefix('hotels')->group(function () {
    Route::get('/', [HotelController::class, 'globalIndex'])
        ->middleware('can:hotels.read')
        ->name('hotels.index');
    Route::post('/', [HotelController::class, 'globalStore'])
        ->middleware('can:hotels.create')
        ->name('hotels.store');
    Route::get('/trash', [HotelController::class, 'globalTrash'])
        ->middleware('can:hotels.delete')
        ->name('hotels.trash');
    Route::post('/trash/{id}/restore', [HotelController::class, 'globalRestore'])
        ->middleware('can:hotels.delete')
        ->name('hotels.restore');
    Route::delete('/trash/{id}', [HotelController::class, 'globalForceDestroy'])
        ->middleware('can:hotels.delete')
        ->name('hotels.force-destroy');
    Route::get('/{hotel}', [HotelController::class, 'globalShow'])
        ->middleware('can:hotels.read')
        ->name('hotels.show');
    Route::put('/{hotel}', [HotelController::class, 'globalUpdate'])
        ->middleware('can:hotels.update')
        ->name('hotels.update');
    Route::delete('/{hotel}', [HotelController::class, 'globalDestroy'])
        ->middleware('can:hotels.delete')
        ->name('hotels.destroy');
});

// Hotel & Reservation management endpoints (nested under event, authenticated + verified)
Route::middleware(['auth:sanctum', 'verified', 'hotel-reservation-enabled'])->prefix('events/{event}')->group(function () {
    // Hotels CRUD (event-scoped via pivot - attach/detach/edit pivot fields)
    Route::prefix('hotels')->group(function () {
        Route::get('/', [HotelController::class, 'index'])
            ->middleware('can:hotels.read')
            ->name('events.hotels.index');
        Route::post('/', [HotelController::class, 'store'])
            ->middleware('can:hotels.create')
            ->name('events.hotels.store');
        Route::get('/{hotel}', [HotelController::class, 'show'])
            ->middleware('can:hotels.read')
            ->name('events.hotels.show');
        Route::put('/{hotel}', [HotelController::class, 'update'])
            ->middleware('can:hotels.update')
            ->name('events.hotels.update');
        Route::delete('/{hotel}', [HotelController::class, 'destroy'])
            ->middleware('can:hotels.delete')
            ->name('events.hotels.destroy');
        Route::post('/{hotel}/media/{collection}/reorder', [HotelController::class, 'reorderMedia'])
            ->middleware('can:hotels.update')
            ->name('events.hotels.media.reorder');

        // Nested room types — `scopeBindings()` so `{roomType}` is resolved
        // against the parent `{hotel}` relation, not by global slug lookup.
        // Without it, two hotels under the same project that both have a
        // "Deluxe" room (slug `deluxe`) collide: Laravel returns whichever
        // RoomType it finds first, and the hotel-id mismatch in the
        // controller surfaces as a misleading 404 on update/show/delete.
        Route::prefix('/{hotel}/room-types')->scopeBindings()->group(function () {
            Route::get('/', [RoomTypeController::class, 'index'])
                ->middleware('can:room_types.read')
                ->name('events.hotels.room-types.index');
            Route::post('/', [RoomTypeController::class, 'store'])
                ->middleware('can:room_types.create')
                ->name('events.hotels.room-types.store');
            Route::get('/{roomType}', [RoomTypeController::class, 'show'])
                ->middleware('can:room_types.read')
                ->name('events.hotels.room-types.show');
            Route::put('/{roomType}', [RoomTypeController::class, 'update'])
                ->middleware('can:room_types.update')
                ->name('events.hotels.room-types.update');
            Route::delete('/{roomType}', [RoomTypeController::class, 'destroy'])
                ->middleware('can:room_types.delete')
                ->name('events.hotels.room-types.destroy');
            Route::post('/{roomType}/media/reorder', [RoomTypeController::class, 'reorderMedia'])
                ->middleware('can:room_types.update')
                ->name('events.hotels.room-types.media.reorder');
        });

        // Nested allotments — scopeBindings prevents allotment slug
        // collisions across hotels from resolving to the wrong record.
        Route::prefix('/{hotel}/allotments')->scopeBindings()->group(function () {
            Route::get('/', [AllotmentController::class, 'index'])
                ->middleware('can:allotments.read')
                ->name('events.hotels.allotments.index');
            Route::post('/', [AllotmentController::class, 'store'])
                ->middleware('can:allotments.create')
                ->name('events.hotels.allotments.store');
            Route::get('/{allotment}', [AllotmentController::class, 'show'])
                ->middleware('can:allotments.read')
                ->name('events.hotels.allotments.show');
            Route::put('/{allotment}', [AllotmentController::class, 'update'])
                ->middleware('can:allotments.update')
                ->name('events.hotels.allotments.update');
            Route::delete('/{allotment}', [AllotmentController::class, 'destroy'])
                ->middleware('can:allotments.delete')
                ->name('events.hotels.allotments.destroy');
        });

        // Nested transfer options — scopeBindings: same rationale.
        Route::prefix('/{hotel}/transfer-options')->scopeBindings()->group(function () {
            Route::get('/', [HotelTransferOptionController::class, 'index'])
                ->middleware('can:hotels.read')
                ->name('events.hotels.transfer-options.index');
            Route::post('/', [HotelTransferOptionController::class, 'store'])
                ->middleware('can:hotels.update')
                ->name('events.hotels.transfer-options.store');
            Route::get('/{transferOption}', [HotelTransferOptionController::class, 'show'])
                ->middleware('can:hotels.read')
                ->name('events.hotels.transfer-options.show');
            Route::put('/{transferOption}', [HotelTransferOptionController::class, 'update'])
                ->middleware('can:hotels.update')
                ->name('events.hotels.transfer-options.update');
            Route::delete('/{transferOption}', [HotelTransferOptionController::class, 'destroy'])
                ->middleware('can:hotels.update')
                ->name('events.hotels.transfer-options.destroy');
        });
    });

    // Reservations (admin, nested under event)
    Route::prefix('reservations')->group(function () {
        Route::get('/', [ReservationController::class, 'index'])
            ->middleware('can:reservations.read')
            ->name('events.reservations.index');
        Route::get('/export', [ReservationController::class, 'export'])
            ->middleware('can:reservations.export')
            ->name('events.reservations.export');
        Route::post('/manual', [ReservationController::class, 'storeManual'])
            ->middleware('can:reservations.manual_entry')
            ->name('events.reservations.store-manual');
        Route::delete('/bulk', [ReservationController::class, 'bulkDestroy'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.bulk-destroy');
        Route::get('/trash', [ReservationController::class, 'trash'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.trash');
        Route::post('/trash/restore/bulk', [ReservationController::class, 'bulkRestore'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.bulk-restore');
        Route::post('/trash/{id}/restore', [ReservationController::class, 'restore'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.restore');
        Route::delete('/trash/bulk', [ReservationController::class, 'bulkForceDestroy'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.bulk-force-destroy');
        Route::delete('/trash/{id}', [ReservationController::class, 'forceDestroy'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.force-destroy');
        Route::get('/{reservation}', [ReservationController::class, 'show'])
            ->middleware('can:reservations.read')
            ->name('events.reservations.show');
        Route::delete('/{reservation}', [ReservationController::class, 'destroy'])
            ->middleware('can:reservations.delete')
            ->name('events.reservations.destroy');
        Route::post('/{reservation}/voucher', [ReservationController::class, 'uploadVoucher'])
            ->middleware('can:reservations.upload_voucher')
            ->name('events.reservations.upload-voucher');
        Route::delete('/{reservation}/voucher', [ReservationController::class, 'deleteVoucher'])
            ->middleware('can:reservations.upload_voucher')
            ->name('events.reservations.delete-voucher');
        Route::post('/{reservation}/send-voucher', [ReservationController::class, 'sendVoucher'])
            ->middleware('can:reservations.send_voucher')
            ->name('events.reservations.send-voucher');
        Route::post('/{reservation}/cancel', [ReservationController::class, 'cancel'])
            ->middleware('can:reservations.cancel')
            ->name('events.reservations.cancel');
        Route::post('/{reservation}/mark-paid', [ReservationController::class, 'markPaid'])
            ->middleware('can:reservations.mark_paid')
            ->name('events.reservations.mark-paid');
        Route::post('/{reservation}/manual-refund', [ReservationController::class, 'manualRefund'])
            ->middleware('can:reservations.refund')
            ->name('events.reservations.manual-refund');
        Route::get('/{reservation}/invoice.pdf', [ReservationController::class, 'invoicePdf'])
            ->middleware('can:reservations.view_documents')
            ->name('events.reservations.invoice-pdf');
        Route::get('/{reservation}/receipt.pdf', [ReservationController::class, 'receiptPdf'])
            ->middleware('can:reservations.view_documents')
            ->name('events.reservations.receipt-pdf');
        Route::get('/{reservation}/activity', [ReservationController::class, 'activityLog'])
            ->middleware('can:reservations.read')
            ->name('events.reservations.activity');

        Route::post('/{reservation}/adjustments', [ReservationAdjustmentController::class, 'store'])
            ->middleware('can:promotions.apply_manual')
            ->name('events.reservations.adjustments.store');
        Route::delete('/{reservation}/adjustments/{adjustment:ulid}', [ReservationAdjustmentController::class, 'destroy'])
            ->middleware('can:promotions.void_adjustment')
            ->name('events.reservations.adjustments.destroy');
    });
});

// Per-event ticket settings — NOT toggle-gated, since this is where the
// `tickets_enabled` flag is read and flipped (gating it would be circular).
Route::middleware(['auth:sanctum', 'verified'])->prefix('events/{event}')->group(function () {
    Route::get('ticket-settings', [EventTicketSettingsController::class, 'show'])
        ->middleware('can:events.read')
        ->name('events.ticket-settings.show');
    Route::put('ticket-settings', [EventTicketSettingsController::class, 'update'])
        ->middleware('can:events.update')
        ->name('events.ticket-settings.update');
});

// Ticketing management endpoints (nested under event, authenticated + verified + toggle-gated)
Route::middleware(['auth:sanctum', 'verified', 'tickets-enabled'])->prefix('events/{event}')->group(function () {
    // Event days
    Route::prefix('event-days')->group(function () {
        Route::get('/', [EventDayController::class, 'index'])
            ->middleware('can:event_days.read')->name('events.event-days.index');
        Route::post('/', [EventDayController::class, 'store'])
            ->middleware('can:event_days.create')->name('events.event-days.store');
        Route::post('/reorder', [EventDayController::class, 'reorder'])
            ->middleware('can:event_days.update')->name('events.event-days.reorder');
        Route::post('/sync', [EventDayController::class, 'sync'])
            ->middleware('can:event_days.create')->name('events.event-days.sync');
        Route::post('/active', [EventDayController::class, 'setActive'])
            ->middleware('can:event_days.update')->name('events.event-days.set-active');
        Route::get('/{eventDay}', [EventDayController::class, 'show'])
            ->scopeBindings()->middleware('can:event_days.read')->name('events.event-days.show');
        Route::put('/{eventDay}', [EventDayController::class, 'update'])
            ->scopeBindings()->middleware('can:event_days.update')->name('events.event-days.update');
        Route::delete('/{eventDay}', [EventDayController::class, 'destroy'])
            ->scopeBindings()->middleware('can:event_days.delete')->name('events.event-days.destroy');
    });

    // Tickets CRUD (event-scoped)
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index'])
            ->middleware('can:tickets.read')->name('events.tickets.index');
        Route::post('/', [TicketController::class, 'store'])
            ->middleware('can:tickets.create')->name('events.tickets.store');
        Route::post('/reorder', [TicketController::class, 'reorder'])
            ->middleware('can:tickets.update')->name('events.tickets.reorder');

        // Bulk generate complimentary tickets (admin) + batch progress/export.
        Route::post('/bulk-generate', [TicketController::class, 'bulkGenerate'])
            ->middleware('can:tickets.bulk_generate')->name('events.tickets.bulk-generate');
        Route::get('/batches/{ticketOrder}/status', [TicketController::class, 'batchStatus'])
            ->middleware('can:tickets.bulk_generate')->name('events.tickets.batches.status');
        Route::get('/batches/{ticketOrder}/export', [TicketController::class, 'exportBatch'])
            ->middleware('can:tickets.bulk_generate')->name('events.tickets.batches.export');

        Route::get('/{ticket}', [TicketController::class, 'show'])
            ->scopeBindings()->middleware('can:tickets.read')->name('events.tickets.show');
        Route::put('/{ticket}', [TicketController::class, 'update'])
            ->scopeBindings()->middleware('can:tickets.update')->name('events.tickets.update');
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])
            ->scopeBindings()->middleware('can:tickets.delete')->name('events.tickets.destroy');

        // Nested price phases — scopeBindings resolves {pricePhase} against {ticket}.
        Route::prefix('/{ticket}/price-phases')->scopeBindings()->group(function () {
            Route::get('/', [TicketPricePhaseController::class, 'index'])
                ->middleware('can:ticket_price_phases.read')->name('events.tickets.price-phases.index');
            Route::post('/', [TicketPricePhaseController::class, 'store'])
                ->middleware('can:ticket_price_phases.create')->name('events.tickets.price-phases.store');
            Route::post('/reorder', [TicketPricePhaseController::class, 'reorder'])
                ->middleware('can:ticket_price_phases.update')->name('events.tickets.price-phases.reorder');
            Route::get('/{pricePhase}', [TicketPricePhaseController::class, 'show'])
                ->middleware('can:ticket_price_phases.read')->name('events.tickets.price-phases.show');
            Route::put('/{pricePhase}', [TicketPricePhaseController::class, 'update'])
                ->middleware('can:ticket_price_phases.update')->name('events.tickets.price-phases.update');
            Route::delete('/{pricePhase}', [TicketPricePhaseController::class, 'destroy'])
                ->middleware('can:ticket_price_phases.delete')->name('events.tickets.price-phases.destroy');
        });

        // Nested sessions (add-on tickets) — scopeBindings resolves {session} against {ticket}.
        Route::prefix('/{ticket}/sessions')->scopeBindings()->group(function () {
            Route::get('/', [TicketSessionController::class, 'index'])
                ->middleware('can:ticket_sessions.read')->name('events.tickets.sessions.index');
            Route::post('/', [TicketSessionController::class, 'store'])
                ->middleware('can:ticket_sessions.create')->name('events.tickets.sessions.store');
            Route::post('/reorder', [TicketSessionController::class, 'reorder'])
                ->middleware('can:ticket_sessions.update')->name('events.tickets.sessions.reorder');
            Route::get('/{session}', [TicketSessionController::class, 'show'])
                ->middleware('can:ticket_sessions.read')->name('events.tickets.sessions.show');
            Route::put('/{session}', [TicketSessionController::class, 'update'])
                ->middleware('can:ticket_sessions.update')->name('events.tickets.sessions.update');
            Route::delete('/{session}', [TicketSessionController::class, 'destroy'])
                ->middleware('can:ticket_sessions.delete')->name('events.tickets.sessions.destroy');
        });
    });

    // Access codes (event-scoped). store = generate a batch of codes.
    Route::prefix('access-codes')->scopeBindings()->group(function () {
        Route::get('/', [AccessCodeController::class, 'index'])
            ->middleware('can:access_codes.read')->name('events.access-codes.index');
        Route::post('/', [AccessCodeController::class, 'store'])
            ->middleware('can:access_codes.generate')->name('events.access-codes.store');
        Route::get('/export', [AccessCodeController::class, 'export'])
            ->middleware('can:access_codes.export')->name('events.access-codes.export');
        Route::get('/{accessCode}', [AccessCodeController::class, 'show'])
            ->middleware('can:access_codes.read')->name('events.access-codes.show');
        Route::put('/{accessCode}', [AccessCodeController::class, 'update'])
            ->middleware('can:access_codes.update')->name('events.access-codes.update');
        Route::delete('/{accessCode}', [AccessCodeController::class, 'destroy'])
            ->middleware('can:access_codes.delete')->name('events.access-codes.destroy');
        Route::post('/{accessCode}/revoke', [AccessCodeController::class, 'revoke'])
            ->middleware('can:access_codes.revoke')->name('events.access-codes.revoke');
        Route::get('/{accessCode}/redemptions', [AccessCodeController::class, 'redemptions'])
            ->middleware('can:access_codes.read')->name('events.access-codes.redemptions');
        Route::post('/{accessCode}/send-invite', [AccessCodeController::class, 'sendInvite'])
            ->middleware('can:access_codes.send_invites')->name('events.access-codes.send-invite');
    });
});

// Scanner / redeem endpoints (scanner, staff, admin, master via scan.check_in).
Route::middleware(['auth:sanctum', 'verified', 'tickets-enabled', 'can:scan.check_in'])
    ->prefix('events/{event}/scan')->group(function () {
        Route::get('/context', [ScanController::class, 'context'])->name('events.scan.context');
        Route::post('/check-in', [ScanController::class, 'checkIn'])->name('events.scan.check-in');
        Route::get('/search', [ScanController::class, 'search'])->name('events.scan.search');
        Route::post('/manual-check-in', [ScanController::class, 'manualCheckIn'])->name('events.scan.manual-check-in');
        Route::get('/manifest', [ScanController::class, 'manifest'])->name('events.scan.manifest');
        Route::post('/sync', [ScanController::class, 'sync'])->name('events.scan.sync');
    });

// Business-matching field builder (admin, event-scoped).
Route::middleware(['auth:sanctum', 'verified', 'tickets-enabled'])->prefix('events/{event}/custom-fields')->group(function () {
    Route::get('/', [EventCustomFieldController::class, 'index'])->middleware('can:event_custom_fields.read')->name('events.custom-fields.index');
    Route::post('/', [EventCustomFieldController::class, 'store'])->middleware('can:event_custom_fields.create')->name('events.custom-fields.store');
    Route::post('/reorder', [EventCustomFieldController::class, 'reorder'])->middleware('can:event_custom_fields.update')->name('events.custom-fields.reorder');
    Route::get('/{customField}', [EventCustomFieldController::class, 'show'])->scopeBindings()->middleware('can:event_custom_fields.read')->name('events.custom-fields.show');
    Route::put('/{customField}', [EventCustomFieldController::class, 'update'])->scopeBindings()->middleware('can:event_custom_fields.update')->name('events.custom-fields.update');
    Route::delete('/{customField}', [EventCustomFieldController::class, 'destroy'])->scopeBindings()->middleware('can:event_custom_fields.delete')->name('events.custom-fields.destroy');
});

// Staff attendee management (list + edit + soft-delete/trash/export; mirrors reservations).
Route::middleware(['auth:sanctum', 'verified'])->prefix('events/{event}/attendees')->group(function () {
    Route::get('/', [EventAttendeeController::class, 'index'])->middleware('can:attendees.read')->name('events.attendees.index');
    Route::get('/export', [EventAttendeeController::class, 'export'])->middleware('can:attendees.export')->name('events.attendees.export');
    Route::get('/analytics/summary', [EventAttendeeAnalyticsController::class, 'summary'])->middleware('can:attendees.read')->name('events.attendees.analytics.summary');
    Route::get('/analytics', [EventAttendeeAnalyticsController::class, 'detail'])->middleware('can:attendees.read')->name('events.attendees.analytics.detail');
    Route::delete('/bulk', [EventAttendeeController::class, 'bulkDestroy'])->middleware('can:attendees.delete')->name('events.attendees.bulk-destroy');
    Route::post('/bulk-check-in', [EventAttendeeController::class, 'bulkCheckIn'])->middleware('can:attendees.update')->name('events.attendees.bulk-check-in');
    Route::post('/bulk-resend-eticket', [EventAttendeeController::class, 'bulkResendETicket'])->middleware('can:attendees.update')->name('events.attendees.bulk-resend-eticket');
    Route::get('/trash', [EventAttendeeController::class, 'trash'])->middleware('can:attendees.delete')->name('events.attendees.trash');
    Route::post('/trash/restore/bulk', [EventAttendeeController::class, 'bulkRestore'])->middleware('can:attendees.delete')->name('events.attendees.bulk-restore');
    Route::post('/trash/{id}/restore', [EventAttendeeController::class, 'restore'])->middleware('can:attendees.delete')->name('events.attendees.restore');
    Route::delete('/trash/bulk', [EventAttendeeController::class, 'bulkForceDestroy'])->middleware('can:attendees.delete')->name('events.attendees.bulk-force-destroy');
    Route::delete('/trash/{id}', [EventAttendeeController::class, 'forceDestroy'])->middleware('can:attendees.delete')->name('events.attendees.force-destroy');
    Route::post('/{attendee}/resend-eticket', [EventAttendeeController::class, 'resendETicket'])->middleware('can:attendees.update')->name('events.attendees.resend-eticket');
    Route::get('/{attendee}/preview-eticket', [EventAttendeeController::class, 'previewAttendeeEmail'])->middleware('can:attendees.view_documents')->name('events.attendees.preview-eticket');
    Route::patch('/{attendee}', [EventAttendeeController::class, 'update'])->middleware('can:attendees.update')->name('events.attendees.update');
    Route::delete('/{attendee}', [EventAttendeeController::class, 'destroy'])->middleware('can:attendees.delete')->name('events.attendees.destroy');
});

// Per-order ticket documents (invoice / receipt PDF) used by the Attendees table.
Route::middleware(['auth:sanctum', 'verified'])->prefix('events/{event}/ticket-orders')->group(function () {
    Route::get('/{order}/invoice.pdf', [EventAttendeeController::class, 'invoicePdf'])->middleware('can:attendees.view_documents')->name('events.ticket-orders.invoice-pdf');
    Route::get('/{order}/receipt.pdf', [EventAttendeeController::class, 'receiptPdf'])->middleware('can:attendees.view_documents')->name('events.ticket-orders.receipt-pdf');
    Route::get('/{order}/preview-confirmation', [EventAttendeeController::class, 'previewOrderEmail'])->middleware('can:attendees.view_documents')->name('events.ticket-orders.preview-confirmation');
    Route::post('/{order}/resend-confirmation', [EventAttendeeController::class, 'resendOrderConfirmation'])->middleware('can:attendees.update')->name('events.ticket-orders.resend-confirmation');
});

// Exhibitor leads (own brand only; data isolation enforced in the controller).
Route::middleware(['auth:sanctum', 'verified'])->prefix('exhibitor/brands/{brand}')->group(function () {
    Route::post('/leads/scan', [ExhibitorLeadController::class, 'scan'])->name('exhibitor.leads.scan');
    Route::get('/leads', [ExhibitorLeadController::class, 'index'])->name('exhibitor.leads.index');
    Route::get('/leads/analytics', [ExhibitorLeadController::class, 'analytics'])->name('exhibitor.leads.analytics');
    Route::get('/leads/context', [ExhibitorLeadController::class, 'context'])->name('exhibitor.leads.context');
    Route::get('/leads/export', [ExhibitorLeadController::class, 'export'])->name('exhibitor.leads.export');
});

// App Settings + Event Branding (root-level, authenticated + verified)
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // App Settings (branding global)
    Route::get('/app-settings/{key}', [AppSettingController::class, 'show'])->name('app-settings.show');
    Route::put('/app-settings/{key}', [AppSettingController::class, 'update'])
        ->middleware('can:app_settings.update')
        ->name('app-settings.update');

    // Flush the public response cache (post-deploy refresh, no SSH needed)
    Route::post('/system/response-cache/clear', [ResponseCacheController::class, 'clear'])
        ->middleware('can:admin.settings')
        ->name('system.response-cache.clear');

    // Sync permissions from config to database (post-deploy, no SSH needed)
    Route::post('/system/permissions/sync', [SyncPermissionsController::class, 'sync'])
        ->middleware('can:admin.settings')
        ->name('system.permissions.sync');

    // Send a test WhatsApp template message (admin/master tool; role gate in the
    // form request). Verifies the Meta Cloud API setup without a real reservation.
    Route::post('/system/whatsapp/test', [WhatsAppTestController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('system.whatsapp.test');

    // Project Branding (per-project PDF branding override)
    Route::get('/projects/{project}/branding', [ProjectBrandingController::class, 'show'])->name('projects.branding.show');
    Route::put('/projects/{project}/branding', [ProjectBrandingController::class, 'update'])
        ->middleware('can:events.update_branding')
        ->name('projects.branding.update');

    // Project payment gateways (Xendit, etc.) - credentials encrypted at rest, masked in API
    Route::prefix('projects/{project:username}/payment-gateways')->group(function () {
        Route::get('/', [ProjectPaymentGatewayController::class, 'index'])->name('projects.payment-gateways.index');
        Route::post('/', [ProjectPaymentGatewayController::class, 'store'])->name('projects.payment-gateways.store');
        Route::post('/test-connection', [ProjectPaymentGatewayController::class, 'testConnection'])
            ->middleware('throttle:30,1')
            ->name('projects.payment-gateways.test-connection');
        Route::get('/{paymentGateway}', [ProjectPaymentGatewayController::class, 'show'])->name('projects.payment-gateways.show');
        Route::patch('/{paymentGateway}', [ProjectPaymentGatewayController::class, 'update'])->name('projects.payment-gateways.update');
        Route::delete('/{paymentGateway}', [ProjectPaymentGatewayController::class, 'destroy'])->name('projects.payment-gateways.destroy');

        // Provider money operations - keyed by the gateway record, provider-agnostic.
        Route::get('/{paymentGateway}/balance', [PaymentGatewayBalanceController::class, 'show'])
            ->middleware('throttle:60,1')
            ->name('projects.payment-gateways.balance');
        Route::get('/{paymentGateway}/transactions', [PaymentGatewayTransactionController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('projects.payment-gateways.transactions');
        Route::get('/{paymentGateway}/transactions/export', [PaymentGatewayTransactionController::class, 'export'])
            ->middleware('throttle:20,1')
            ->name('projects.payment-gateways.transactions.export');
        Route::get('/{paymentGateway}/webhook-events', [PaymentGatewayWebhookEventController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('projects.payment-gateways.webhook-events');
        Route::get('/{paymentGateway}/reconciliation', [PaymentGatewayReconciliationController::class, 'index'])
            ->middleware('throttle:20,1')
            ->name('projects.payment-gateways.reconciliation');
        Route::get('/{paymentGateway}/settlement', [PaymentGatewaySettlementController::class, 'show'])
            ->middleware('throttle:30,1')
            ->name('projects.payment-gateways.settlement');
    });
});

// Public Blog API endpoints (API key authentication for consumption by multiple websites)
Route::middleware(['api.key'])->prefix('public/blog')->group(function () {
    // Posts endpoints
    Route::get('/posts', [PublicBlogController::class, 'posts'])
        ->middleware(CacheResponse::for(3600, 'blog-posts'));
    Route::get('/posts/featured', [PublicBlogController::class, 'featured'])
        ->middleware(CacheResponse::for(3600, 'blog-posts'));
    Route::get('/posts/search', [PublicBlogController::class, 'search'])
        ->middleware(CacheResponse::for(1800, 'blog-posts'));
    Route::get('/posts/{slug}', [PublicBlogController::class, 'post']); // No cache - has trackVisit

    // Categories endpoints (uses Spatie Tags with type 'category')
    Route::get('/categories/{slug}/posts', [PublicBlogController::class, 'postsByCategory'])
        ->middleware(CacheResponse::for(3600, 'blog-posts'));

    // Tags endpoints
    Route::get('/tags/{tag}/posts', [PublicBlogController::class, 'postsByTag'])
        ->middleware(CacheResponse::for(3600, 'blog-posts'));

    // Authors endpoints
    Route::get('/authors/{username}/posts', [PublicBlogController::class, 'postsByAuthor'])
        ->middleware(CacheResponse::for(3600, 'blog-posts'));
});

// Public Project & Event API endpoints (API key authentication)
Route::middleware(['api.key'])->prefix('public/projects')->group(function () {
    Route::get('/{username}', [PublicProjectController::class, 'show'])
        ->middleware(CacheResponse::for(86400, 'projects'));
    Route::get('/{username}/events', [PublicProjectController::class, 'events'])
        ->middleware(CacheResponse::for(86400, 'events'));
    Route::get('/{username}/events/active', [PublicProjectController::class, 'activeEvent'])
        ->middleware(CacheResponse::for(86400, 'events'));
    Route::get('/{username}/events/{eventSlug}', [PublicProjectController::class, 'event'])
        ->middleware(CacheResponse::for(86400, 'events'));
    Route::get('/{username}/editions', [PublicProjectController::class, 'publishedEditions'])
        ->middleware(CacheResponse::for(86400, 'events'));
    Route::get('/{username}/editions/{editionNumber}/brands', [PublicProjectController::class, 'brandsByEdition'])
        ->where('editionNumber', '[0-9]+')
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/editions/{editionNumber}/brands/{brandSlug}', [PublicProjectController::class, 'brandByEdition'])
        ->where('editionNumber', '[0-9]+')
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/brands-with-conjunctions', [PublicProjectController::class, 'activeBrandsWithConjunctions'])
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/brands', [PublicProjectController::class, 'activeBrands'])
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/brands/{brandSlug}', [PublicProjectController::class, 'activeBrand'])
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/events/{eventSlug}/partners', [PublicProjectController::class, 'partners'])
        ->middleware(CacheResponse::for(86400, 'partners'));
    Route::get('/{username}/editions/{editionNumber}/partners', [PublicProjectController::class, 'partnersByEdition'])
        ->where('editionNumber', '[0-9]+')
        ->middleware(CacheResponse::for(86400, 'partners'));
    Route::get('/{username}/events/{eventSlug}/brands', [PublicProjectController::class, 'brands'])
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/events/{eventSlug}/brands/{brandSlug}', [PublicProjectController::class, 'brand'])
        ->middleware(CacheResponse::for(86400, 'brands'));
    Route::get('/{username}/events/{eventSlug}/brands/{brandSlug}/promotion-posts', [PublicProjectController::class, 'promotionPosts'])
        ->middleware(CacheResponse::for(3600, 'promotion-posts'));
    Route::get('/{username}/events/{eventSlug}/rundown', [PublicProjectController::class, 'rundown'])
        ->middleware(CacheResponse::for(86400, 'rundown'));
    Route::get('/{username}/editions/{editionNumber}/rundown', [PublicProjectController::class, 'rundownByEdition'])
        ->where('editionNumber', '[0-9]+')
        ->middleware(CacheResponse::for(86400, 'rundown'));
    Route::get('/{username}/events/{eventSlug}/programs', [PublicProjectController::class, 'programs'])
        ->middleware(CacheResponse::for(86400, 'programs'));
    Route::get('/{username}/events/{eventSlug}/faqs', [PublicProjectController::class, 'faqs'])
        ->middleware(CacheResponse::for(86400, 'faqs'));
    Route::get('/{username}/events/{eventSlug}/media-coverages', [PublicProjectController::class, 'mediaCoverages'])
        ->middleware(CacheResponse::for(86400, 'media-coverages'));
    Route::get('/{username}/events/{eventSlug}/gallery', [PublicProjectController::class, 'gallery'])
        ->middleware(CacheResponse::for(86400, 'gallery'));
    Route::get('/{username}/website-settings', [PublicProjectController::class, 'websiteSettings'])
        ->middleware(CacheResponse::for(86400, 'website-settings'));
    Route::get('/{username}/events/{eventSlug}/guests', [PublicProjectController::class, 'guests'])
        ->middleware(CacheResponse::for(86400, 'guests'));
    Route::get('/{username}/events/{eventSlug}/guests/{slug}', [PublicProjectController::class, 'guest'])
        ->middleware(CacheResponse::for(86400, 'guests'));
});

// Public Exchange Rate API endpoints (no authentication required, public proxy)
Route::prefix('exchange-rates')->middleware('throttle:api')->group(function () {
    Route::get('/', [ExchangeRateController::class, 'index'])
        ->middleware(CacheResponse::for(3600, 'exchange-rates'));
    Route::get('/currencies', [ExchangeRateController::class, 'currencies'])
        ->middleware(CacheResponse::for(86400, 'exchange-rates'));
    Route::get('/popular', [ExchangeRateController::class, 'popular'])
        ->middleware(CacheResponse::for(3600, 'exchange-rates'));
    Route::get('/convert', [ExchangeRateController::class, 'convert'])
        ->middleware(CacheResponse::for(3600, 'exchange-rates'));
    Route::get('/{currency}', [ExchangeRateController::class, 'show'])
        ->middleware(CacheResponse::for(3600, 'exchange-rates'));
});

// Public Hotel Reservation API endpoints (API key authentication)
Route::middleware(['api.key'])->prefix('public')->group(function () {
    Route::get('/banners', [PublicBannerController::class, 'index'])
        ->middleware(CacheResponse::for(3600, 'banners'));
    Route::get('/hotels', [PublicHotelController::class, 'index'])
        ->middleware(CacheResponse::for(3600, 'hotels'));
    Route::post('/hotels/availability', [PublicHotelController::class, 'availability'])
        ->middleware(['throttle:60,1', 'hotel-reservation-enabled']);
    Route::get('/events/{eventSlug}/hotels/{hotelSlug}', [PublicHotelController::class, 'show'])
        ->middleware(['hotel-reservation-enabled', CacheResponse::for(3600, 'hotels')]);
    Route::get(
        '/events/{eventSlug}/hotels/{hotelSlug}/room-types/{roomTypeId}/daily-availability',
        [PublicHotelController::class, 'dailyAvailability']
    )->middleware(['throttle:60,1', 'hotel-reservation-enabled']);
    Route::get(
        '/events/{eventSlug}/hotels/{hotelSlug}/daily-availability-aggregate',
        [PublicHotelController::class, 'dailyAvailabilityAggregate']
    )->middleware(['throttle:60,1', 'hotel-reservation-enabled']);
    Route::post('/reservations', [PublicReservationController::class, 'store'])
        ->middleware(['throttle:10,1', 'hotel-reservation-enabled']);
    Route::post('/reservations/preview-pricing', [PublicReservationController::class, 'previewPricing'])
        ->middleware(['throttle:60,1', 'hotel-reservation-enabled']);
    Route::get('/reservations/magic/{token}', [PublicReservationController::class, 'showByMagicLink'])
        ->middleware('throttle:30,1');
    Route::get('/reservations/status/{reservationNumber}', [PublicReservationController::class, 'statusByNumber'])
        ->middleware('throttle:30,1');
    Route::post('/reservations/magic/{token}/retry-payment', [PublicReservationController::class, 'retryPaymentByMagicLink'])
        ->middleware('throttle:5,1');

    Route::post('/promo-codes/validate', [PublicPromoCodeController::class, 'validate'])
        ->middleware('throttle:30,1');

    // ── Tickets (event websites) ──────────────────────────────────────────
    Route::get('/events/{eventSlug}/tickets', [PublicTicketController::class, 'index'])
        ->middleware(['tickets-enabled', CacheResponse::for(300, 'tickets')]);
    Route::get('/events/{eventSlug}/custom-fields', [PublicTicketController::class, 'customFields'])
        ->middleware(['tickets-enabled', CacheResponse::for(300, 'tickets')]);
    Route::post('/tickets/preview', [PublicTicketController::class, 'preview'])
        ->middleware(['throttle:60,1', 'tickets-enabled']);
    // Uncached + throttled: the ONLY way a hidden ticket is revealed (anti brute-force).
    Route::post('/tickets/validate-access-code', [PublicAccessCodeController::class, 'validate'])
        ->middleware(['throttle:access-code', 'tickets-enabled']);
    Route::post('/tickets/email-lookup', [PublicTicketController::class, 'emailLookup'])
        ->middleware('throttle:30,1');
    Route::post('/ticket-orders', [PublicTicketOrderController::class, 'store'])
        ->middleware(['throttle:10,1', 'tickets-enabled']);
    Route::get('/ticket-orders/{ulid}', [PublicTicketOrderController::class, 'show'])
        ->middleware('throttle:30,1');
    Route::get('/ticket-orders/magic/{token}', [PublicTicketOrderController::class, 'showByMagicLink'])
        ->middleware('throttle:30,1');
    Route::get('/ticket-orders/magic/{token}/invoice.pdf', [PublicTicketOrderController::class, 'invoicePdfByMagicLink'])
        ->middleware('throttle:30,1')
        ->name('public.ticket-orders.invoice-pdf');
    Route::get('/ticket-orders/magic/{token}/receipt.pdf', [PublicTicketOrderController::class, 'receiptPdfByMagicLink'])
        ->middleware('throttle:30,1')
        ->name('public.ticket-orders.receipt-pdf');

    // E-ticket per attendee (shareable; ulid is the access key).
    Route::get('/attendees/{ulid}', [PublicAttendeeController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::patch('/attendees/{ulid}', [PublicAttendeeController::class, 'personalize'])
        ->middleware('throttle:30,1');
    Route::post('/attendees/{ulid}/dashboard-link', [PublicAttendeeController::class, 'dashboardLink'])
        ->middleware('throttle:10,1')
        ->name('public.attendees.dashboard-link');
});

// Visitor dashboard (authenticated buyer/holder).
Route::middleware(['auth:sanctum', 'verified'])->prefix('my')->group(function () {
    Route::get('/ticket-orders', [MyTicketsController::class, 'orders'])->name('my.ticket-orders');
    Route::get('/tickets', [MyTicketsController::class, 'tickets'])->name('my.tickets');
    Route::patch('/attendees/{ulid}', [MyTicketsController::class, 'updateAttendee'])->name('my.attendees.update');
    Route::get('/ticket-profile', [MyTicketsController::class, 'profile'])->name('my.ticket-profile.show');
    Route::patch('/ticket-profile', [MyTicketsController::class, 'updateProfile'])->name('my.ticket-profile.update');
    Route::get('/events/{event}/field-responses', [MyTicketsController::class, 'fieldResponses'])->name('my.field-responses.show');
    Route::put('/events/{event}/field-responses', [MyTicketsController::class, 'saveFieldResponses'])->name('my.field-responses.save');
});

// Magic-link document downloads — opened directly from reservation emails in
// the guest's browser, so they cannot carry an API key. The unguessable
// magic-link token (plus the per-token rate limit in resolveByToken) is the
// authentication, so these routes sit outside the api.key group.
Route::prefix('public')->group(function () {
    Route::get('/reservations/magic/{token}/invoice.pdf', [PublicReservationController::class, 'invoicePdfByMagicLink'])
        ->middleware('throttle:30,1');
    Route::get('/reservations/magic/{token}/receipt.pdf', [PublicReservationController::class, 'receiptPdfByMagicLink'])
        ->middleware('throttle:30,1');
    Route::get('/reservations/magic/{token}/voucher', [PublicReservationController::class, 'voucherByMagicLink'])
        ->middleware('throttle:30,1');

    // QR code embedded directly in the attendee e-ticket email as a plain <img>.
    // The email client fetches it without an API key, so it sits outside the
    // api.key group; the unguessable opaque ulid (plus throttle) is the access
    // key, same trade-off as the shareable e-ticket page.
    Route::get('/attendees/{ulid}/qr.png', [PublicAttendeeController::class, 'qrImage'])
        ->middleware('throttle:120,1')
        ->name('public.attendees.qr-image');
});

// Xendit webhook (no auth - signature verified inside the controller).
// Two variants:
//   1) Per-project URL `/api/webhooks/xendit/{username}` — original shape;
//      project resolved from the URL segment, token verified against that
//      project's gateway.
//   2) Generic URL `/api/webhooks/xendit` — required when multiple PM One
//      projects share the SAME Xendit account (one Xendit dashboard only
//      accepts one Invoice-Paid URL). The controller resolves the project
//      from the payload's `external_id` (= reservation_number) and verifies
//      the token against that project's gateway.
// Xendit accepts a single webhook URL per event type. Three valid shapes:
//   1) `/api/webhooks/xendit`            — generic; resolves project via payload
//   2) `/api/webhooks/xendit/{anything}` — tolerant; tries project lookup by
//      that segment first, otherwise treats it as a Xendit event-type marker
//      (invoice / refund / ewallet / fva / etc.) and falls back to generic
//   3) `/api/webhooks/xendit/{username}` — per-project (subset of #2)
// One handler entry point keeps the resolution rules in one place — adding new
// Xendit event types later requires no route changes.
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'invoiceGeneric'])
    ->middleware('log-payment-webhook:xendit')
    ->name('webhooks.xendit.invoice-generic');
Route::post('/webhooks/xendit/{segment}', [XenditWebhookController::class, 'invoiceWithSegment'])
    ->middleware('log-payment-webhook:xendit')
    ->name('webhooks.xendit.invoice');

// Midtrans webhook (no auth - SHA512 signature verified inside the controller).
// Midtrans posts HTTP notifications to a single "Payment Notification URL"; the
// reservation is resolved from `order_id` in the payload. The `{segment}`
// variant tolerates a dashboard misconfiguration (trailing path) by ignoring it.
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handle'])
    ->middleware('log-payment-webhook:midtrans')
    ->name('webhooks.midtrans');
Route::post('/webhooks/midtrans/{segment}', [MidtransWebhookController::class, 'handleWithSegment'])
    ->middleware('log-payment-webhook:midtrans')
    ->name('webhooks.midtrans.segment');
