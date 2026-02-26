<?php

namespace App\Http\Controllers\Api;

use App\Exports\BrandEventsExport;
use App\Exports\BrandEventsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\PromotionPostResource;
use App\Imports\BrandEventsImport;
use App\Mail\ExhibitorInviteMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\User;
use App\Notifications\BrandInvitedToEventNotification;
use App\Notifications\PromotionPostUploadedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandEventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Resolve project by username.
     */
    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    /**
     * Resolve event by slug within project.
     */
    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    /**
     * Resolve brand-event by brand slug within event.
     */
    private function resolveBrandEvent(Event $event, string $brandSlug): BrandEvent
    {
        return $event->brandEvents()
            ->with('brand')
            ->whereHas('brand', fn ($q) => $q->where('slug', $brandSlug))
            ->firstOrFail();
    }

    /**
     * Find or create a user by email.
     */
    private function findOrCreateUser(string $email): User
    {
        $email = strtolower(trim($email));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            return $user;
        }

        $password = Str::random(12);

        return User::create([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make($password),
            'encrypted_password' => Crypt::encryptString($password),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * List brands in an event.
     */
    public function index(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $query = $event->brandEvents()->with(['brand.media', 'brand.tags', 'sales'])->withCount('promotionPosts');

        // Search
        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->whereHas('brand', function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('company_name', 'ilike', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('filter.status')) {
            $statuses = explode(',', $request->input('filter.status'));
            $query->whereIn('status', $statuses);
        }

        // Sorting
        $sort = $request->input('sort', 'order_column');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy($field, $direction);

        // Client-only mode: return all data without pagination
        if ($request->boolean('client_only')) {
            $brandEvents = $query->get();

            return response()->json([
                'data' => BrandEventIndexResource::collection($brandEvents),
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $brandEvents->count(),
                    'total' => $brandEvents->count(),
                ],
            ]);
        }

        $brandEvents = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => BrandEventIndexResource::collection($brandEvents->items()),
            'meta' => [
                'current_page' => $brandEvents->currentPage(),
                'last_page' => $brandEvents->lastPage(),
                'per_page' => $brandEvents->perPage(),
                'total' => $brandEvents->total(),
            ],
        ]);
    }

    /**
     * Add brand to event (create or attach + invite).
     */
    public function store(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'booth_size' => ['nullable', 'numeric', 'min:0'],
            'booth_price' => ['nullable', 'numeric', 'min:0'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email'],
            'send_login_email' => ['nullable', 'boolean'],
        ]);

        $brandName = trim($validated['brand_name']);

        // Find existing brand by name (case-insensitive)
        $brand = Brand::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($brandName)])->first();

        if ($brand) {
            // Check if already in this event
            $existingBrandEvent = BrandEvent::where('brand_id', $brand->id)
                ->where('event_id', $event->id)
                ->first();

            if ($existingBrandEvent) {
                return response()->json([
                    'message' => 'Brand is already registered in this event.',
                    'data' => new BrandEventResource($existingBrandEvent->load(['brand.media', 'brand.tags', 'brand.users.media', 'sales', 'promotionPosts.media'])),
                ], 409);
            }
        } else {
            // Create new brand
            $brand = Brand::create([
                'name' => $brandName,
            ]);
        }

        // Attach brand to event
        $brandEvent = BrandEvent::create([
            'brand_id' => $brand->id,
            'event_id' => $event->id,
            'booth_size' => $validated['booth_size'] ?? null,
            'booth_price' => $validated['booth_price'] ?? null,
            'sales_id' => $validated['sales_id'] ?? null,
        ]);

        // Process emails - find or create users and attach to brand
        $emails = $validated['emails'] ?? [];
        foreach ($emails as $email) {
            $user = $this->findOrCreateUser($email);

            // Assign exhibitor role if doesn't have it
            if (! $user->hasRole('exhibitor')) {
                $user->assignRole('exhibitor');
            }

            // Attach to brand if not already
            if (! $brand->users()->where('user_id', $user->id)->exists()) {
                $brand->users()->attach($user->id);
            }
        }

        // Send login emails if requested
        if (! empty($validated['send_login_email'])) {
            foreach ($emails as $email) {
                $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
                if ($user) {
                    $plainPassword = $user->encrypted_password ? Crypt::decryptString($user->encrypted_password) : null;
                    Mail::to($user->email)->send(new ExhibitorInviteMail($user, $brand, $event, $plainPassword));

                    // Clear encrypted password after sending
                    if ($user->encrypted_password) {
                        $user->update(['encrypted_password' => null]);
                    }
                }
            }
        }

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.users.media', 'sales', 'promotionPosts.media']);

        // Notify brand members about event invitation
        foreach ($brand->users as $brandUser) {
            $brandUser->notify(new BrandInvitedToEventNotification($brand, $event));
        }

        return response()->json([
            'message' => 'Brand added to event successfully.',
            'data' => new BrandEventResource($brandEvent),
        ], 201);
    }

    /**
     * Show brand-event detail.
     */
    public function show(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.users.media', 'sales', 'promotionPosts.media']);

        // Load custom field definitions and values
        $customFieldDefinitions = $project->customFields()->ordered()->get();

        return response()->json([
            'data' => new BrandEventResource($brandEvent),
            'project_custom_field_definitions' => $customFieldDefinitions,
            'brand_custom_fields' => $brandEvent->brand->custom_fields ?? (object) [],
        ]);
    }

    /**
     * Update booth info (staff only).
     */
    public function update(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $this->authorize('updateBooth', $brandEvent);

        $validated = $request->validate([
            'booth_number' => ['nullable', 'string', 'max:50'],
            'booth_size' => ['nullable', 'numeric', 'min:0'],
            'booth_price' => ['nullable', 'numeric', 'min:0'],
            'booth_type' => ['nullable', 'string', 'max:50'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'promotion_post_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $brandEvent->update($validated);

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.users.media', 'sales', 'promotionPosts.media']);

        return response()->json([
            'message' => 'Brand event updated successfully.',
            'data' => new BrandEventResource($brandEvent),
        ]);
    }

    /**
     * Update brand profile.
     */
    public function updateProfile(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $this->authorize('update', $brandEvent);

        $brand = $brandEvent->brand;
        $oldDescription = $brand->description;

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'custom_fields' => ['nullable', 'array'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string'],
            'project_custom_fields' => ['nullable', 'array'],
        ]);

        // Update brand fields
        $brandFields = collect($validated)->only([
            'name', 'description', 'company_name', 'company_address',
            'company_email', 'company_phone', 'custom_fields',
        ])->filter(fn ($value) => $value !== null)->toArray();

        if (! empty($brandFields)) {
            $brand->update($brandFields);
        }

        // Handle brand logo upload
        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        // Process content images in description
        $this->processContentImages($brand);

        // Cleanup removed content images
        $this->cleanupRemovedContentImages($brand, $oldDescription);

        // Sync business categories if provided
        if (isset($validated['business_categories'])) {
            $brand->syncBusinessCategories($validated['business_categories']);
        }

        // Save project custom field values to brands.custom_fields
        if (isset($validated['project_custom_fields'])) {
            $customFieldDefinitions = $project->customFields()->get();
            $cleanedValues = $brand->custom_fields ?? [];

            foreach ($customFieldDefinitions as $fieldDef) {
                if (array_key_exists($fieldDef->key, $validated['project_custom_fields'])) {
                    $cleanedValues[$fieldDef->key] = $validated['project_custom_fields'][$fieldDef->key];
                }
            }

            $brand->update(['custom_fields' => $cleanedValues]);
        }

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.users.media', 'sales', 'promotionPosts.media']);

        // Reload custom field data for response
        $customFieldDefinitions = $project->customFields()->ordered()->get();

        return response()->json([
            'message' => 'Brand profile updated successfully.',
            'data' => new BrandEventResource($brandEvent),
            'project_custom_field_definitions' => $customFieldDefinitions,
            'brand_custom_fields' => $brand->fresh()->custom_fields ?? (object) [],
        ]);
    }

    /**
     * Detach brand from event.
     */
    public function destroy(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $brandEvent->delete();

        return response()->json([
            'message' => 'Brand removed from event successfully.',
        ]);
    }

    /**
     * Reorder brands in event.
     */
    public function updateOrder(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:brand_event,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE brand_event SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND event_id = ?",
            [...$params, $event->id]
        );

        return response()->json([
            'message' => 'Brand order updated successfully.',
        ]);
    }

    /**
     * List brand members.
     */
    public function members(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $brand = $brandEvent->brand;
        $members = $brand->users()->with('media')->get();

        return response()->json([
            'data' => $members,
        ]);
    }

    /**
     * Add member to brand.
     */
    public function addMember(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'send_login_email' => ['nullable', 'boolean'],
        ]);

        $brand = $brandEvent->brand;
        $user = $this->findOrCreateUser($validated['email']);

        // Assign exhibitor role if doesn't have it
        if (! $user->hasRole('exhibitor')) {
            $user->assignRole('exhibitor');
        }

        // Attach to brand if not already
        if (! $brand->users()->where('user_id', $user->id)->exists()) {
            $brand->users()->attach($user->id);
        }

        // Send login email if requested
        if (! empty($validated['send_login_email'])) {
            $plainPassword = $user->encrypted_password ? Crypt::decryptString($user->encrypted_password) : null;
            Mail::to($user->email)->send(new ExhibitorInviteMail($user, $brand, $event, $plainPassword));

            // Clear encrypted password after sending
            if ($user->encrypted_password) {
                $user->update(['encrypted_password' => null]);
            }
        }

        return response()->json([
            'message' => 'Member added to brand successfully.',
            'data' => $user->load('media'),
        ], 201);
    }

    /**
     * Remove member from brand.
     */
    public function removeMember(string $username, string $eventSlug, string $brandSlug, int $userId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $brand = $brandEvent->brand;
        $brand->users()->detach($userId);

        return response()->json([
            'message' => 'Member removed from brand successfully.',
        ]);
    }

    /**
     * Resend invite email to a brand member.
     */
    public function sendInvite(string $username, string $eventSlug, string $brandSlug, int $userId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $brand = $brandEvent->brand;
        $user = $brand->users()->findOrFail($userId);

        $plainPassword = $user->encrypted_password ? Crypt::decryptString($user->encrypted_password) : null;
        Mail::to($user->email)->send(new ExhibitorInviteMail($user, $brand, $event, $plainPassword));

        // Clear encrypted password after sending
        if ($user->encrypted_password) {
            $user->update(['encrypted_password' => null]);
        }

        return response()->json([
            'message' => 'Invite email sent successfully.',
        ]);
    }

    /**
     * List promotion posts for a brand-event.
     */
    public function promotionPosts(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $posts = $brandEvent->promotionPosts()->with('media')->ordered()->get();

        return response()->json([
            'data' => PromotionPostResource::collection($posts),
        ]);
    }

    /**
     * Create a promotion post.
     */
    public function storePromotionPost(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        // Check deadline - only enforce for non-staff users
        if ($event->promotion_post_deadline && $event->promotion_post_deadline->isPast() && ! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            return response()->json(['message' => 'Promotion post deadline has passed.'], 422);
        }

        // Check promotion post limit (only for non-staff)
        if (! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            $currentCount = $brandEvent->promotionPosts()->count();
            if ($currentCount >= $brandEvent->promotion_post_limit) {
                return response()->json(['message' => 'Promotion post limit reached.'], 422);
            }
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string'],
            'tmp_post_images' => ['nullable', 'array', 'max:20'],
            'tmp_post_images.*' => ['string'],
        ]);

        $post = PromotionPost::create([
            'brand_event_id' => $brandEvent->id,
            'caption' => $validated['caption'] ?? null,
        ]);

        // Handle post image uploads
        $this->handleTemporaryUploads($request, $post, 'tmp_post_images', 'post_image');

        // Notify staff/admin users about new promotion post
        $brandEvent->load(['brand', 'event']);
        $staffUsers = User::role(['master', 'admin', 'staff'])->get();
        foreach ($staffUsers as $staffUser) {
            if ($staffUser->id !== $request->user()->id) {
                $staffUser->notify(new PromotionPostUploadedNotification($brandEvent, $request->user()));
            }
        }

        return response()->json([
            'message' => 'Promotion post created successfully.',
            'data' => new PromotionPostResource($post->load('media')),
        ], 201);
    }

    /**
     * Update a promotion post.
     */
    public function updatePromotionPost(Request $request, string $username, string $eventSlug, string $brandSlug, int $postId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $post = $brandEvent->promotionPosts()->findOrFail($postId);

        $validated = $request->validate([
            'caption' => ['nullable', 'string'],
            'tmp_post_images' => ['nullable', 'array', 'max:20'],
            'tmp_post_images.*' => ['string'],
            'delete_media_ids' => ['nullable', 'array'],
            'delete_media_ids.*' => ['integer'],
        ]);

        $post->update(collect($validated)->only(['caption'])->toArray());

        // Delete specific media items
        if ($request->has('delete_media_ids')) {
            $post->media()
                ->where('collection_name', 'post_image')
                ->whereIn('id', $request->input('delete_media_ids'))
                ->get()
                ->each(fn ($media) => $media->delete());
        }

        // Handle post image uploads
        $this->handleTemporaryUploads($request, $post, 'tmp_post_images', 'post_image');

        return response()->json([
            'message' => 'Promotion post updated successfully.',
            'data' => new PromotionPostResource($post->load('media')),
        ]);
    }

    /**
     * Delete a promotion post.
     */
    public function destroyPromotionPost(string $username, string $eventSlug, string $brandSlug, int $postId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $post = $brandEvent->promotionPosts()->findOrFail($postId);
        $post->delete();

        return response()->json([
            'message' => 'Promotion post deleted successfully.',
        ]);
    }

    /**
     * Reorder media images within a promotion post.
     */
    public function reorderPromotionPostMedia(Request $request, string $username, string $eventSlug, string $brandSlug, int $postId): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $post = $brandEvent->promotionPosts()->findOrFail($postId);

        $validated = $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer'],
        ]);

        \Spatie\MediaLibrary\MediaCollections\Models\Media::setNewOrder($validated['media_ids']);

        $post->load('media');

        return response()->json([
            'message' => 'Media order updated.',
            'data' => new PromotionPostResource($post),
        ]);
    }

    /**
     * Reorder promotion posts.
     */
    public function updatePromotionPostOrder(Request $request, string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', 'exists:promotion_posts,id'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $cases = [];
        $ids = [];
        $params = [];

        foreach ($validated['orders'] as $orderData) {
            $cases[] = 'WHEN id = ? THEN ?::integer';
            $params[] = $orderData['id'];
            $params[] = $orderData['order'];
            $ids[] = $orderData['id'];
        }

        $idsString = implode(',', $ids);
        $casesString = implode(' ', $cases);

        \DB::statement(
            "UPDATE promotion_posts SET order_column = CASE {$casesString} END WHERE id IN ({$idsString}) AND brand_event_id = ?",
            [...$params, $brandEvent->id]
        );

        return response()->json([
            'message' => 'Promotion post order updated successfully.',
        ]);
    }

    /**
     * Export brands in event as Excel.
     */
    public function export(Request $request, string $username, string $eventSlug): BinaryFileResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $filters = [];
        if ($search = $request->input('filter_search')) {
            $filters['search'] = $search;
        }
        if ($status = $request->input('filter_status')) {
            $filters['status'] = $status;
        }

        $sort = $request->input('sort', 'order_column');

        $export = new BrandEventsExport($event->id, $filters, $sort);
        $filename = 'brands_'.now()->format('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }

    /**
     * Download brand-event import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new BrandEventsTemplateExport, 'brand_events_import_template.xlsx');
    }

    /**
     * Import brands into event from Excel/CSV.
     */
    public function import(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tempFolder = null;

        try {
            $tempFolder = $request->input('file');

            // Get file path from temporary storage
            $metadataPath = "tmp/uploads/{$tempFolder}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            $metadata = json_decode(
                Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$tempFolder}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            // Import brand events
            $import = new BrandEventsImport($event->id);
            Excel::import($import, Storage::disk('local')->path($filePath));

            // Get import results
            $failures = $import->getFailures();
            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errorMessages = [];

            foreach ($failures as $failure) {
                $errorMessages[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values(),
                ];
            }

            if (count($errorMessages) > 0) {
                return response()->json([
                    'message' => 'Import completed with errors',
                    'errors' => $errorMessages,
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                ], 422);
            }

            $message = "Brands imported successfully ({$importedCount} imported";
            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} skipped (already in event)";
            }
            $message .= ')';

            activity()
                ->performedOn($event)
                ->causedBy($request->user())
                ->withProperties([
                    'project_id' => $project->id,
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                ])
                ->event('imported')
                ->log("Imported {$importedCount} brands to event {$event->title}");

            return response()->json([
                'message' => $message,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
            ]);
        } catch (\Exception $e) {
            logger()->error('Brand event import failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to import brands',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            // Always clean up temporary files
            if ($tempFolder) {
                Storage::disk('local')->deleteDirectory("tmp/uploads/{$tempFolder}");
            }
        }
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, $model, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $model->clearMediaCollection($collection);

            return;
        }

        if (! $request->has($fieldName)) {
            return;
        }

        $value = $request->input($fieldName);

        if (! $value) {
            return;
        }

        if (! Str::startsWith($value, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$value}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(
            Storage::disk('local')->get($metadataPath),
            true
        );

        $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $model->clearMediaCollection($collection);

        $model->addMedia(Storage::disk('local')->path($filePath))
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
    }

    /**
     * Handle multiple temporary file uploads and add to media collection.
     */
    private function handleTemporaryUploads(Request $request, $model, string $fieldName, string $collection): void
    {
        if (! $request->has($fieldName)) {
            return;
        }

        $values = $request->input($fieldName);

        if (! is_array($values) || empty($values)) {
            return;
        }

        foreach ($values as $value) {
            if (! $value || ! Str::startsWith($value, 'tmp-')) {
                continue;
            }

            $metadataPath = "tmp/uploads/{$value}/metadata.json";

            if (! Storage::disk('local')->exists($metadataPath)) {
                continue;
            }

            $metadata = json_decode(
                Storage::disk('local')->get($metadataPath),
                true
            );

            $filePath = "tmp/uploads/{$value}/{$metadata['original_name']}";

            if (! Storage::disk('local')->exists($filePath)) {
                continue;
            }

            $model->addMedia(Storage::disk('local')->path($filePath))
                ->toMediaCollection($collection);

            Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");
        }
    }

    /**
     * Process content images - move temporary images to permanent storage.
     */
    private function processContentImages($model, string $field = 'description', string $collection = 'description_images'): void
    {
        if (! $model->{$field}) {
            return;
        }

        $content = $model->{$field};
        $pattern = '/<img[^>]+src="(?:https?:\/\/[^\/]+)?\/api\/tmp-media\/(tmp-media-[a-zA-Z0-9._-]+)"[^>]*>/';

        if (! preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $match) {
            $fullImgTag = $match[0];
            $folder = $match[1];

            try {
                $metadataPath = "tmp/uploads/{$folder}/metadata.json";

                if (! Storage::disk('local')->exists($metadataPath)) {
                    continue;
                }

                $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
                $filename = $metadata['original_name'];
                $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

                if (! Storage::disk('local')->exists($tempFilePath)) {
                    continue;
                }

                $caption = null;
                if (preg_match('/data-caption="([^"]*)"/', $fullImgTag, $captionMatch)) {
                    $caption = html_entity_decode($captionMatch[1]);
                }

                $mediaAdder = $model->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName(pathinfo($filename, PATHINFO_FILENAME));

                if ($caption) {
                    $mediaAdder->withCustomProperties(['caption' => $caption]);
                }

                $media = $mediaAdder->toMediaCollection($collection);

                $responsiveImg = $this->buildResponsiveImageHtml($media, $caption);
                $content = str_replace($fullImgTag, $responsiveImg, $content);

                Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
            } catch (\Exception $e) {
                logger()->warning('Failed to process content image', [
                    'folder' => $folder,
                    'error' => $e->getMessage(),
                    'model_type' => get_class($model),
                    'model_id' => $model->id,
                ]);
            }
        }

        if ($content !== $model->{$field}) {
            $model->update([$field => $content]);
        }
    }

    /**
     * Build responsive image HTML with srcset for content images.
     */
    private function buildResponsiveImageHtml($media, ?string $caption = null): string
    {
        $alt = $caption ?? $media->getCustomProperty('caption') ?? $media->name;

        $srcset = [
            $media->getUrl('sm').' 600w',
            $media->getUrl('md').' 900w',
            $media->getUrl('lg').' 1200w',
            $media->getUrl('xl').' 1600w',
        ];

        $srcsetString = implode(', ', $srcset);
        $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 90vw, 1200px';

        $captionAttr = $caption
            ? sprintf(' data-caption="%s"', htmlspecialchars($caption, ENT_QUOTES, 'UTF-8'))
            : '';

        $html = sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s"%s loading="lazy" class="w-full h-auto rounded-lg">',
            $media->getUrl('lg'),
            $srcsetString,
            $sizes,
            htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'),
            $captionAttr
        );

        return $html;
    }

    /**
     * Cleanup content images that were removed from content.
     */
    private function cleanupRemovedContentImages($model, ?string $oldContent, string $collection = 'description_images'): void
    {
        $contentImages = $model->getMedia($collection);

        if ($contentImages->isEmpty()) {
            return;
        }

        $currentContent = $model->description ?? '';

        foreach ($contentImages as $media) {
            if (! $this->isMediaUsedInContent($media, $currentContent)) {
                try {
                    $media->delete();
                } catch (\Exception $e) {
                    logger()->warning('Failed to cleanup removed description image', [
                        'model_type' => get_class($model),
                        'model_id' => $model->id,
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Check if a media file is used in content.
     */
    private function isMediaUsedInContent($media, string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $filename = $media->file_name;

        if (str_contains($content, $filename)) {
            return true;
        }

        $encodedFilename = rawurlencode($filename);
        if (str_contains($content, $encodedFilename)) {
            return true;
        }

        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (str_contains($content, $baseName)) {
            return true;
        }

        return false;
    }
}
