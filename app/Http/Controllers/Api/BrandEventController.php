<?php

namespace App\Http\Controllers\Api;

use App\Enums\BoothType;
use App\Exports\BrandEventsExport;
use App\Exports\BrandEventsTemplateExport;
use App\Helpers\LinkNormalizer;
use App\Helpers\PhoneCountryHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandEventIndexResource;
use App\Http\Resources\BrandEventResource;
use App\Http\Resources\EventDocumentResource;
use App\Http\Resources\EventDocumentSubmissionResource;
use App\Http\Resources\ProjectCustomFieldResource;
use App\Http\Resources\PromotionPostResource;
use App\Imports\BrandEventsImport;
use App\Jobs\BulkPermanentDeleteBrands;
use App\Jobs\ProcessExcelImport;
use App\Mail\ExhibitorInviteMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventDocumentSubmission;
use App\Models\Project;
use App\Models\PromotionPost;
use App\Models\User;
use App\Notifications\BrandInvitedToEventNotification;
use App\Notifications\PromotionPostUploadedNotification;
use App\Support\CustomFieldValidation;
use App\Support\ImageDimensions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\Tags\Tag;
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

        $user = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

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

        $query = $event->brandEvents()
            ->with(['brand.media', 'brand.tags', 'brand.links', 'sales'])
            ->withCount([
                'promotionPosts',
                'visits',
                'promotionPosts as posts_with_caption_count' => fn ($q) => $q->whereNotNull('caption')->where('caption', '!=', ''),
                'promotionPosts as posts_with_image_count' => fn ($q) => $q->whereHas('media', fn ($m) => $m->where('collection_name', 'post_image')),
            ]);

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
            'company_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'booth_type' => ['nullable', 'string', Rule::in(array_column(BoothType::cases(), 'value'))],
            'booth_number' => ['nullable', 'string', 'max:255'],
            'booth_size' => ['nullable', 'numeric', 'min:0'],
            'booth_price' => ['nullable', 'numeric', 'min:0'],
            'currency_override' => ['nullable', 'in:IDR,USD'],
            'badge_name' => ['nullable', 'string', 'max:255'],
            'fascia_name' => ['nullable', 'string', 'max:255'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'emails' => ['nullable', 'array'],
            'emails.*' => [Email::default()],
            'send_login_email' => ['nullable', 'boolean'],
        ]);

        // Force fascia_name uppercase
        if (! empty($validated['fascia_name'])) {
            $validated['fascia_name'] = strtoupper($validated['fascia_name']);
        }

        $brandName = trim($validated['brand_name']);
        $companyName = trim($validated['company_name'] ?? '');
        $country = trim($validated['country'] ?? '');

        // Find existing brand by name (case-insensitive)
        $brand = Brand::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($brandName)])->first();

        if ($brand) {
            // Update company_name if provided and brand doesn't have one
            if ($companyName && ! $brand->company_name) {
                $brand->update(['company_name' => $companyName]);
            }
            // Fill country into the brand address if provided and brand doesn't have one
            if ($country && empty($brand->address['country'] ?? null)) {
                $address = $brand->address ?? [];
                $address['country'] = $country;
                $brand->update(['address' => $address]);
            }
            // Check if already in this event
            $existingBrandEvent = BrandEvent::where('brand_id', $brand->id)
                ->where('event_id', $event->id)
                ->first();

            if ($existingBrandEvent) {
                return response()->json([
                    'message' => 'Brand is already registered in this event.',
                    'data' => new BrandEventResource($existingBrandEvent->load(['brand.media', 'brand.tags', 'brand.links', 'brand.creator', 'brand.updater', 'brand.users.media', 'sales', 'promotionPosts.media'])),
                ], 409);
            }
        } else {
            // Create new brand
            $brand = Brand::create([
                'name' => $brandName,
                'company_name' => $companyName ?: null,
                'address' => $country ? ['country' => $country] : null,
            ]);
        }

        // Attach brand to event
        $brandEvent = BrandEvent::create([
            'brand_id' => $brand->id,
            'event_id' => $event->id,
            'booth_type' => $validated['booth_type'] ?? null,
            'booth_number' => $validated['booth_number'] ?? null,
            'booth_size' => $validated['booth_size'] ?? null,
            'booth_price' => $validated['booth_price'] ?? null,
            'currency_override' => $validated['currency_override'] ?? null,
            'badge_name' => $validated['badge_name'] ?? null,
            'fascia_name' => $validated['fascia_name'] ?? null,
            'sales_id' => $validated['sales_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
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

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.links', 'brand.creator', 'brand.updater', 'brand.users.media', 'sales', 'promotionPosts.media']);

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

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.links', 'brand.creator', 'brand.updater', 'brand.users.media', 'sales', 'promotionPosts.media']);

        // Load custom field definitions and values
        $customFieldDefinitions = $project->customFields()->get();

        // Load predefined business category options for this project
        $businessCategoryOptions = Tag::withType("business_category:{$project->id}")
            ->ordered()
            ->pluck('name')
            ->toArray();

        return response()->json([
            'data' => new BrandEventResource($brandEvent),
            'project_custom_field_definitions' => ProjectCustomFieldResource::collection($customFieldDefinitions),
            'brand_custom_fields' => $brandEvent->brand->custom_fields ?? (object) [],
            'business_category_options' => $businessCategoryOptions,
        ]);
    }

    /**
     * List document submissions for a brand-event (staff view).
     */
    public function documentSubmissions(string $username, string $eventSlug, string $brandSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);
        $brandEvent = $this->resolveBrandEvent($event, $brandSlug);

        $boothIdentifier = $brandEvent->booth_number ?: "be-{$brandEvent->id}";

        $documents = $event->eventDocuments()
            ->with(['media', 'fields'])
            ->ordered()
            ->get()
            ->filter(fn ($doc) => $doc->appliesToBoothType($brandEvent->booth_type?->value));

        $submissions = EventDocumentSubmission::query()
            ->where('event_id', $event->id)
            ->where('booth_identifier', $boothIdentifier)
            ->with(['submitter', 'media'])
            ->get()
            ->keyBy('event_document_id');

        $data = $documents->map(function ($doc) use ($submissions) {
            $submission = $submissions->get($doc->id);

            $status = 'pending';
            if ($submission) {
                if ($submission->document_version < $doc->content_version) {
                    $status = 'needs_reagreement';
                } elseif ($doc->isSubmissionComplete($submission)) {
                    $status = 'completed';
                }
            }

            return [
                'document' => new EventDocumentResource($doc),
                'submission' => $submission ? new EventDocumentSubmissionResource($submission) : null,
                'status' => $status,
                // Upload history, grouped by field, newest version first. This
                // is the audit trail for file re-uploads.
                'file_history' => $submission ? $submission->fileHistory() : [],
            ];
        })->values();

        return response()->json(['data' => $data]);
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
            'currency_override' => ['nullable', 'in:IDR,USD'],
            'booth_type' => ['nullable', 'string', 'max:50'],
            'sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'promotion_post_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fascia_name' => ['nullable', 'string', 'max:255'],
            'badge_name' => ['nullable', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ]);

        $brandEvent->update($validated);

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.links', 'brand.creator', 'brand.updater', 'brand.users.media', 'sales', 'promotionPosts.media']);

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
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:1000'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', Email::default(), 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'custom_fields' => ['nullable', 'array'],
            'tmp_profile_image' => ['nullable', 'string'],
            'delete_profile_image' => ['nullable', 'boolean'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string'],
            'project_custom_fields' => ['nullable', 'array'],
            'links' => ['nullable', 'array'],
            'links.*.label' => ['required', 'string', 'max:100'],
            'links.*.url' => ['required', 'string', 'max:500'],
        ]);

        // Normalize input data
        if (isset($validated['company_email'])) {
            $validated['company_email'] = strtolower(trim($validated['company_email']));
        }
        if (isset($validated['company_phone']) && $validated['company_phone'] !== null) {
            $validated['company_phone'] = PhoneCountryHelper::normalizePhoneNumber($validated['company_phone']);
        }
        foreach (['name', 'company_name'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        // Update brand fields
        $brandFields = collect($validated)->only([
            'name', 'description', 'company_name', 'address',
            'company_email', 'company_phone', 'custom_fields',
        ])->filter(fn ($value) => $value !== null)->toArray();

        // A null address means "clear it", which the filter above would drop.
        if ($request->has('address')) {
            $brandFields['address'] = $validated['address'] ?? null;
        }

        if (! empty($brandFields)) {
            $brand->update($brandFields);
        }

        // Handle avatar + raw master logo uploads
        $this->handleTemporaryUpload($request, $brand, 'tmp_profile_image', 'profile_image');
        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        // Process content images in description
        $this->processContentImages($brand);

        // Cleanup removed content images
        $this->cleanupRemovedContentImages($brand, $oldDescription);

        // Sync business categories if provided (scoped to this project)
        if (isset($validated['business_categories'])) {
            $brand->syncBusinessCategories($validated['business_categories'], $project->id);
        }

        // Save project custom field values to brands.custom_fields; values are
        // only validated when the payload actually carries the key, so partial
        // updates that skip brand fields are never blocked.
        if (isset($validated['project_custom_fields'])) {
            $customFieldDefinitions = $project->customFields()->get();

            $errors = CustomFieldValidation::errorsFor(
                $customFieldDefinitions,
                (array) $request->input('project_custom_fields'),
                'project_custom_fields',
                'key',
            );

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $cleanedValues = $brand->custom_fields ?? [];

            foreach ($customFieldDefinitions as $fieldDef) {
                if (array_key_exists($fieldDef->key, $validated['project_custom_fields'])) {
                    $cleanedValues[$fieldDef->key] = $validated['project_custom_fields'][$fieldDef->key];
                }
            }

            $brand->update(['custom_fields' => $cleanedValues]);
        }

        // Sync links if provided
        if ($request->has('links') && is_array($request->links)) {
            $normalizedLinks = LinkNormalizer::normalizeAll($validated['links']);
            $brand->links()->delete();

            foreach ($normalizedLinks as $index => $linkData) {
                $brand->links()->create([
                    'label' => $linkData['label'],
                    'url' => $linkData['url'],
                    'order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        // The brand profile write touches scalar fields, logo, categories, links
        // and custom fields in sequence; the logo-only path even skips the
        // $brand->update() trait clear (empty $brandFields guard above). Clear
        // once after all writes so the public brand payload is never stale.
        ResponseCache::clear(['brands']);

        $brandEvent->load(['brand.media', 'brand.tags', 'brand.links', 'brand.creator', 'brand.updater', 'brand.users.media', 'sales', 'promotionPosts.media']);

        // Reload custom field data for response
        $customFieldDefinitions = $project->customFields()->get();

        // Load predefined business category options for response
        $businessCategoryOptions = Tag::withType("business_category:{$project->id}")
            ->ordered()
            ->pluck('name')
            ->toArray();

        return response()->json([
            'message' => 'Brand profile updated successfully.',
            'data' => new BrandEventResource($brandEvent),
            'project_custom_field_definitions' => ProjectCustomFieldResource::collection($customFieldDefinitions),
            'brand_custom_fields' => $brand->fresh()->custom_fields ?? (object) [],
            'business_category_options' => $businessCategoryOptions,
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
     * Bulk remove brands from event (detach pivot).
     */
    public function bulkDestroy(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'slugs' => ['required', 'array'],
            'slugs.*' => ['string'],
        ]);

        $brandEvents = BrandEvent::where('event_id', $event->id)
            ->whereHas('brand', fn ($q) => $q->whereIn('slug', $validated['slugs']))
            ->get();

        $deletedCount = $brandEvents->count();
        $brandEvents->each->delete();

        return response()->json([
            'message' => "{$deletedCount} brand(s) removed from event successfully.",
        ]);
    }

    /**
     * Bulk permanently delete brands (force delete, bypasses soft delete).
     */
    public function bulkPermanentDelete(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $validated = $request->validate([
            'slugs' => ['required', 'array'],
            'slugs.*' => ['string'],
        ]);

        $jobId = Str::uuid()->toString();

        Cache::put("job:{$jobId}", [
            'status' => 'pending',
            'total' => count($validated['slugs']),
            'processed' => 0,
            'percentage' => 0,
            'message' => 'Preparing to delete brands...',
            'error_message' => null,
        ], now()->addMinutes(30));

        BulkPermanentDeleteBrands::dispatch(
            $jobId,
            $validated['slugs'],
            $event->id,
            auth()->id(),
        );

        return response()->json(['job_id' => $jobId]);
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

        // Raw SQL bypasses Eloquent events, so the BrandEvent ClearsResponseCache
        // trait never fires. The public brand lists order by order_column, so the
        // new order must invalidate the cache manually.
        ResponseCache::clear(['brands', 'promotion-posts']);

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
            'email' => ['required', Email::default()],
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

        // Notify project members + master/admin about new promotion post (exclude uploader)
        $brandEvent->load(['brand', 'event.project']);
        $notifiableUsers = $brandEvent->event->project->getNotifiableUsers(excludeUserId: $request->user()->id);
        foreach ($notifiableUsers as $notifiableUser) {
            $notifiableUser->notify(new PromotionPostUploadedNotification($brandEvent, $request->user()));
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

            // Media delete does not fire the PromotionPost saved event.
            ResponseCache::clear(['brands', 'promotion-posts']);
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

        Media::setNewOrder($validated['media_ids']);

        // Media reorder does not fire the PromotionPost saved event; the brand
        // and promotion previews render media in order, so bust manually.
        ResponseCache::clear(['brands', 'promotion-posts']);

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

        // Raw SQL bypasses Eloquent events; promotion previews on the public
        // brand payloads are ordered by order_column, so bust manually.
        ResponseCache::clear(['brands', 'promotion-posts']);

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

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'project_id' => $event->project_id,
                'model_type' => 'BrandEvent',
                'event_id' => $event->id,
                'filename' => $filename,
            ])
            ->log('Exported event brands');

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

        $tempFolder = $request->input('file');

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

        $importId = Str::uuid()->toString();

        Cache::put("import:{$importId}", [
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'percentage' => 0,
            'errors' => [],
            'error_message' => null,
        ], now()->addMinutes(30));

        ProcessExcelImport::dispatch(
            $importId,
            Storage::disk('local')->path($filePath),
            BrandEventsImport::class,
            $tempFolder,
            [$event->id],
        );

        // Prevent duplicate log entries (e.g. double-click on import button)
        $recentDuplicate = Activity::where('event', 'imported')
            ->where('subject_type', $event->getMorphClass())
            ->where('subject_id', $event->id)
            ->where('causer_id', $request->user()->id)
            ->where('created_at', '>=', now()->subMinutes(1))
            ->exists();

        if (! $recentDuplicate) {
            activity()
                ->performedOn($event)
                ->causedBy($request->user())
                ->withProperties([
                    'project_id' => $project->id,
                ])
                ->event('imported')
                ->log("Started brand import for event {$event->title}");
        }

        return response()->json([
            'import_id' => $importId,
        ]);
    }

    /**
     * Handle temporary file upload and move to media collection.
     */
    private function handleTemporaryUpload(Request $request, $model, string $fieldName, string $collection): void
    {
        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $model->clearMediaCollection($collection);

            // MediaLibrary mutations do not fire the model saved event, so the
            // brand logo change must bust the cached public brand payloads here.
            ResponseCache::clear(['brands']);

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

        $absolutePath = Storage::disk('local')->path($filePath);

        if ($collection === 'profile_image'
            && ! ImageDimensions::meetsMinimum($absolutePath, $metadata['mime_type'] ?? '')) {
            throw ValidationException::withMessages([
                'tmp_profile_image' => 'Profile image must be at least 1000x1000 pixels.',
            ]);
        }

        $model->clearMediaCollection($collection);

        $model->addMedia($absolutePath)
            ->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$value}");

        // Avatar/logo media is committed after the caller's $brand->update(),
        // so bust again here to avoid repopulating the cache with old media.
        ResponseCache::clear(['brands']);
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

        // Promotion-post images appear in the public brand previews and detail
        // payloads. MediaLibrary add does not fire the PromotionPost saved
        // event, so bust the cache after committing the new images.
        ResponseCache::clear(['brands', 'promotion-posts']);
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

                $baseName = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'image';
                $extension = pathinfo($filename, PATHINFO_EXTENSION);

                $mediaAdder = $model->addMediaFromDisk($tempFilePath, 'local')
                    ->usingName($baseName)
                    ->usingFileName($baseName.($extension ? '.'.$extension : ''));

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
