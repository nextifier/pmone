<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitOrderRequest;
use App\Http\Resources\EventDocumentResource;
use App\Http\Resources\EventDocumentSubmissionResource;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PromotionPostResource;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\CustomField;
use App\Models\EventDocumentSubmission;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\PromotionPost;
use App\Notifications\OrderSubmittedNotification;
use App\Services\Order\OrderSubmissionService;
use App\Support\CustomFieldValidation;
use App\Support\CustomFieldValues;
use App\Support\ImageDimensions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ResponseCache\Facades\ResponseCache;
use Spatie\Tags\Tag;

class ExhibitorDashboardController extends Controller
{
    /**
     * Exhibitor dashboard - step-by-step progress per brand-event.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $profileComplete = ! empty($user->name) && ! empty($user->phone) && ! empty($user->title) && ! empty($user->company_name);

        $brands = $user->brands()->with([
            'media',
            'brandEvents' => function ($q) {
                $q->with(['event.media', 'event.eventDocuments' => function ($q2) {
                    $q2->with(['media', 'fields'])->ordered();
                }])
                    ->withCount(['promotionPosts', 'orders']);
            },
        ])->get();

        // Collect all brand-event IDs to batch-load submissions
        $allBrandEvents = $brands->flatMap(fn (Brand $b) => $b->brandEvents);
        $boothIdentifiers = $allBrandEvents->map(fn (BrandEvent $be) => $be->booth_number ?: "be-{$be->id}");
        $eventIds = $allBrandEvents->pluck('event_id')->unique();

        // Batch load all submissions for these events + booths
        $allSubmissions = EventDocumentSubmission::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('booth_identifier', $boothIdentifiers)
            ->with(['media', 'submitter'])
            ->get()
            ->groupBy(fn ($s) => "{$s->event_id}:{$s->booth_identifier}:{$s->event_document_id}");

        $brandEventsData = $brands->flatMap(function (Brand $brand) use ($allSubmissions) {
            $hasAddress = collect($brand->address ?? [])->contains(fn ($value) => filled($value));

            $brandMissingFields = collect([
                'company_name' => 'Company Name',
                'company_email' => 'Email',
                'company_phone' => 'Phone',
                'address' => 'Address',
                'description' => 'Description',
            ])->filter(fn ($label, $field) => $field === 'address'
                ? ! $hasAddress
                : empty($brand->{$field})
            )->values()->all();

            return $brand->brandEvents->map(function (BrandEvent $be) use ($brand, $brandMissingFields, $allSubmissions) {
                $event = $be->event;
                if (! $event) {
                    return null;
                }

                $boothIdentifier = $be->booth_number ?: "be-{$be->id}";
                $boothTypeValue = $be->booth_type?->value;

                // Filter documents by booth type
                $applicableDocs = $event->eventDocuments
                    ->filter(fn ($doc) => $doc->appliesToBoothType($boothTypeValue))
                    ->values();

                // Split: event rules (blocking checkbox) vs operational documents
                $eventRules = $applicableDocs->filter(fn ($doc) => $doc->isEventRule());
                $operationalDocs = $applicableDocs->filter(fn ($doc) => ! $doc->isEventRule());

                // Build event rules with submission status
                $eventRulesData = $eventRules->map(function ($doc) use ($event, $boothIdentifier, $allSubmissions) {
                    $key = "{$event->id}:{$boothIdentifier}:{$doc->id}";
                    $submission = $allSubmissions->get($key)?->first();
                    $needsReagreement = $submission && $submission->document_version < $doc->content_version;

                    return [
                        'document' => new EventDocumentResource($doc),
                        'agreed' => $submission && $submission->agreed_at && ! $needsReagreement,
                        'needs_reagreement' => $needsReagreement,
                        'submission' => $submission ? [
                            'agreed_at' => $submission->agreed_at?->toIso8601String(),
                            'document_version' => $submission->document_version,
                            'submitter_name' => $submission->submitter?->name,
                        ] : null,
                    ];
                })->values();

                $allRulesAgreed = $eventRulesData->every(fn ($r) => $r['agreed']);

                // Build operational documents summary
                $docsData = $operationalDocs->map(function ($doc) use ($event, $boothIdentifier, $allSubmissions) {
                    $key = "{$event->id}:{$boothIdentifier}:{$doc->id}";
                    $submission = $allSubmissions->get($key)?->first();

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
                    ];
                })->values();

                $docsTotal = $docsData->count();
                $docsCompleted = $docsData->where('status', 'completed')->count();

                return [
                    'brand_event_id' => $be->id,
                    'brand' => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                        'profile_image' => $brand->profile_image,
                        'brand_logo' => $brand->brand_logo,
                        'is_complete' => empty($brandMissingFields),
                        'missing_fields' => $brandMissingFields,
                    ],
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'date_label' => $event->date_label,
                        'location' => $event->location,
                        'poster_image' => $event->poster_image,
                    ],
                    'booth_number' => $be->booth_number,
                    'booth_type' => $boothTypeValue,
                    'booth_type_label' => $be->booth_type?->label(),
                    'fascia_name' => $be->fascia_name,
                    'badge_name' => $be->badge_name,
                    'event_rules' => $eventRulesData,
                    'event_rules_agreed' => $allRulesAgreed,
                    'brand_complete' => empty($brandMissingFields),
                    'promotion_posts_count' => $be->promotion_posts_count,
                    'promotion_post_limit' => $be->promotion_post_limit,
                    'promotion_post_deadline' => $event->promotion_post_deadline?->toIso8601String(),
                    'documents' => $docsData,
                    'documents_total' => $docsTotal,
                    'documents_completed' => $docsCompleted,
                    'orders_count' => $be->orders_count,
                    'order_form_deadline' => $event->order_form_deadline?->toIso8601String(),
                    'normal_order_opens_at' => $event->normal_order_opens_at?->toIso8601String(),
                    'normal_order_closes_at' => $event->normal_order_closes_at?->toIso8601String(),
                    'onsite_order_opens_at' => $event->onsite_order_opens_at?->toIso8601String(),
                    'onsite_order_closes_at' => $event->onsite_order_closes_at?->toIso8601String(),
                    'onsite_penalty_rate' => $event->onsite_penalty_rate,
                ];
            })->filter();
        })
            ->sortBy(function ($item) {
                $endDate = $item['event']['date_label'] ?? null;

                return $endDate ?? 'zzz';
            })
            ->values();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'title' => $user->title,
                    'company_name' => $user->company_name,
                ],
                'profile_complete' => $profileComplete,
                'brand_events' => $brandEventsData,
            ],
        ]);
    }

    /**
     * List events the exhibitor participates in, grouped by event.
     */
    public function myEvents(Request $request): JsonResponse
    {
        $user = $request->user();

        $brands = $user->brands()->with([
            'media',
            'brandEvents' => function ($q) {
                $q->with(['event.media'])
                    ->withCount(['promotionPosts', 'orders']);
            },
        ])->get();

        // Group brand-events by event, each event may have multiple brands
        $eventMap = [];

        foreach ($brands as $brand) {
            foreach ($brand->brandEvents as $be) {
                $event = $be->event;
                if (! $event) {
                    continue;
                }

                $eventId = $event->id;

                if (! isset($eventMap[$eventId])) {
                    $eventMap[$eventId] = [
                        'id' => $event->id,
                        'title' => $event->title,
                        'slug' => $event->slug,
                        'date_label' => $event->date_label,
                        'location' => $event->location,
                        'venue' => $event->venue,
                        'start_date' => $event->start_date?->toIso8601String(),
                        'end_date' => $event->end_date?->toIso8601String(),
                        'poster_image' => $event->poster_image,
                        'is_active' => $event->is_active,
                        'brands' => [],
                    ];
                }

                $eventMap[$eventId]['brands'][] = [
                    'brand_event_id' => $be->id,
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'profile_image' => $brand->profile_image,
                    'brand_logo' => $brand->brand_logo,
                    'booth_number' => $be->booth_number,
                    'booth_type' => $be->booth_type?->value,
                    'booth_type_label' => $be->booth_type?->label(),
                    'promotion_posts_count' => $be->promotion_posts_count,
                    'orders_count' => $be->orders_count,
                ];
            }
        }

        // Sort: active events first, then by start_date descending
        $events = collect(array_values($eventMap))
            ->sortByDesc(fn ($e) => ($e['is_active'] ? '1' : '0').($e['start_date'] ?? ''))
            ->values();

        return response()->json(['data' => $events]);
    }

    /**
     * List brands owned by exhibitor.
     */
    public function brands(Request $request): JsonResponse
    {
        $brands = $request->user()->brands()
            ->with(['media', 'brandEvents.event.media'])
            ->get()
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'company_name' => $brand->company_name,
                'profile_image' => $brand->profile_image,
                'brand_logo' => $brand->brand_logo,
                'status' => $brand->status,
                'events_count' => $brand->brandEvents->count(),
            ]);

        return response()->json(['data' => $brands]);
    }

    /**
     * Get brand detail for exhibitor.
     */
    public function brandShow(Request $request, string $brandSlug): JsonResponse
    {
        $brand = $request->user()->brands()
            ->with(['media', 'tags', 'links', 'brandEvents.event.project'])
            ->where('brands.slug', $brandSlug)
            ->firstOrFail();

        // Collect business category options from all projects the brand participates in
        $projectIds = $brand->brandEvents->pluck('event.project.id')->filter()->unique();
        $businessCategoryOptions = [];

        foreach ($projectIds as $projectId) {
            $businessCategoryOptions = array_merge(
                $businessCategoryOptions,
                Tag::withType("business_category:{$projectId}")
                    ->ordered()
                    ->pluck('name')
                    ->toArray()
            );
        }

        $businessCategoryOptions = array_values(array_unique($businessCategoryOptions));

        return response()->json([
            'data' => [
                'id' => $brand->id,
                'ulid' => $brand->ulid,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'company_name' => $brand->company_name,
                'address' => $brand->address,
                'company_email' => $brand->company_email,
                'company_phone' => $brand->company_phone,
                'custom_fields' => $brand->custom_fields,
                'status' => $brand->status,
                'visibility' => $brand->visibility,
                'profile_image' => $brand->profile_image,
                'brand_logo' => $brand->brand_logo,
                'business_categories' => $brand->business_categories_list,
            ],
            'business_category_options' => $businessCategoryOptions,
        ]);
    }

    /**
     * Update brand profile (by exhibitor).
     */
    public function brandUpdate(Request $request, string $brandSlug): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:1000'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.country' => ['nullable', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'custom_fields' => ['nullable', 'array'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string', 'max:100'],
            'tmp_profile_image' => ['nullable', 'string'],
            'delete_profile_image' => ['nullable', 'boolean'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
        ]);

        $categories = $validated['business_categories'] ?? null;
        unset($validated['business_categories'], $validated['tmp_profile_image'], $validated['delete_profile_image'], $validated['tmp_brand_logo'], $validated['delete_brand_logo']);

        $brand->update($validated);

        if ($categories !== null) {
            $brand->syncBusinessCategories($categories);
        }

        $this->handleTemporaryUpload($request, $brand, 'tmp_profile_image', 'profile_image');
        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        $brand->load('media');

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'profile_image' => $brand->profile_image,
                'brand_logo' => $brand->brand_logo,
            ],
        ]);
    }

    /**
     * List events for a brand (exhibitor view).
     */
    public function brandEvents(Request $request, string $brandSlug): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();

        $brandEvents = BrandEvent::query()
            ->with(['event.media'])
            ->withCount('promotionPosts')
            ->where('brand_id', $brand->id)
            ->get()
            ->map(fn (BrandEvent $be) => [
                'id' => $be->id,
                'event' => [
                    'id' => $be->event->id,
                    'title' => $be->event->title,
                    'slug' => $be->event->slug,
                    'date_label' => $be->event->date_label,
                    'location' => $be->event->location,
                    'poster_image' => $be->event->poster_image,
                ],
                'booth_number' => $be->booth_number,
                'booth_type' => $be->booth_type?->value,
                'booth_type_label' => $be->booth_type?->label(),
                'status' => $be->status,
                'promotion_posts_count' => $be->promotion_posts_count,
                'promotion_post_limit' => $be->promotion_post_limit,
            ]);

        return response()->json(['data' => $brandEvents]);
    }

    /**
     * List promotion posts for a brand event.
     */
    public function promotionPosts(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with(['event.media'])
            ->findOrFail($brandEventId);

        $posts = PromotionPost::query()
            ->where('brand_event_id', $brandEvent->id)
            ->with('media')
            ->ordered()
            ->get();

        return response()->json([
            'data' => [
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'profile_image' => $brand->profile_image,
                    'brand_logo' => $brand->brand_logo,
                ],
                'event' => [
                    'id' => $brandEvent->event->id,
                    'title' => $brandEvent->event->title,
                    'date_label' => $brandEvent->event->date_label,
                    'location' => $brandEvent->event->location,
                    'poster_image' => $brandEvent->event->poster_image,
                ],
                'brand_event' => [
                    'id' => $brandEvent->id,
                    'booth_number' => $brandEvent->booth_number,
                    'booth_type' => $brandEvent->booth_type?->value,
                    'status' => $brandEvent->status,
                ],
                'promotion_post_limit' => $brandEvent->promotion_post_limit,
                'promotion_post_deadline' => $brandEvent->event->promotion_post_deadline?->toIso8601String(),
                'posts' => PromotionPostResource::collection($posts),
            ],
        ]);
    }

    /**
     * Create a promotion post.
     */
    public function storePromotionPost(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $brandEvent->loadMissing('event');
        $event = $brandEvent->event;
        if ($event->promotion_post_deadline && $event->promotion_post_deadline->isPast() && ! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            return response()->json(['message' => 'Promotion post deadline has passed.'], 422);
        }

        // Check promotion post limit
        $currentCount = $brandEvent->promotionPosts()->count();
        if ($currentCount >= $brandEvent->promotion_post_limit && ! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            return response()->json(['message' => 'Promotion post limit reached. Contact event organizer to increase your limit.'], 422);
        }

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:5000'],
            'tmp_post_images' => ['nullable', 'array', 'max:20'],
            'tmp_post_images.*' => ['string'],
        ]);

        $post = $brandEvent->promotionPosts()->create([
            'caption' => $validated['caption'] ?? null,
        ]);

        $this->handleTemporaryUploads($request, $post, 'tmp_post_images', 'post_image');

        $post->load('media');

        return response()->json([
            'message' => 'Promotion post created',
            'data' => new PromotionPostResource($post),
        ], 201);
    }

    /**
     * Update a promotion post.
     */
    public function updatePromotionPost(Request $request, string $brandSlug, int $brandEventId, int $postId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $post = PromotionPost::query()
            ->where('brand_event_id', $brandEvent->id)
            ->findOrFail($postId);

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:5000'],
            'tmp_post_images' => ['nullable', 'array', 'max:20'],
            'tmp_post_images.*' => ['string'],
            'delete_media_ids' => ['nullable', 'array'],
            'delete_media_ids.*' => ['integer'],
        ]);

        $post->update([
            'caption' => $validated['caption'] ?? $post->caption,
        ]);

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

        $this->handleTemporaryUploads($request, $post, 'tmp_post_images', 'post_image');

        $post->load('media');

        return response()->json([
            'message' => 'Promotion post updated',
            'data' => new PromotionPostResource($post),
        ]);
    }

    /**
     * Delete a promotion post.
     */
    public function destroyPromotionPost(Request $request, string $brandSlug, int $brandEventId, int $postId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $post = PromotionPost::query()
            ->where('brand_event_id', $brandEvent->id)
            ->findOrFail($postId);

        $post->clearMediaCollection('post_image');
        $post->delete();

        return response()->json(['message' => 'Promotion post deleted']);
    }

    /**
     * Reorder media images within a promotion post.
     */
    public function reorderPromotionPostMedia(Request $request, string $brandSlug, int $brandEventId, int $postId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $post = PromotionPost::query()
            ->where('brand_event_id', $brandEvent->id)
            ->findOrFail($postId);

        $validated = $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer'],
        ]);

        Media::setNewOrder($validated['media_ids']);

        // Media reorder does not fire the PromotionPost saved event.
        ResponseCache::clear(['brands', 'promotion-posts']);

        $post->load('media');

        return response()->json([
            'message' => 'Media order updated.',
            'data' => new PromotionPostResource($post),
        ]);
    }

    /**
     * Get available products for the order form, filtered by exhibitor's booth type.
     */
    public function orderFormProducts(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $currency = $brandEvent->resolveCurrency();

        $query = EventProduct::query()
            ->with(['media', 'productCategory.media'])
            ->where('event_id', $brandEvent->event_id)
            ->where('is_active', true)
            ->ordered();

        // USD catalogs hide products without a USD price.
        if ($currency === 'USD') {
            $query->whereNotNull('price_usd');
        }

        // Filter by booth type if exhibitor has one
        if ($brandEvent->booth_type) {
            $boothTypeValue = $brandEvent->booth_type->value;
            $query->where(function ($q) use ($boothTypeValue) {
                $q->whereNull('booth_types')
                    ->orWhereJsonContains('booth_types', $boothTypeValue);
            });
        }

        $products = $query->get();

        // Group by category
        $grouped = $products->groupBy(fn ($p) => $p->productCategory?->title ?? 'Uncategorized')->map(function ($items, $category) use ($currency) {
            $firstCategory = $items->first()?->productCategory;
            $catalogFile = $firstCategory?->getMediaUrls('catalog_files');

            return [
                'category' => $category,
                'description' => $firstCategory?->description,
                'catalog_file' => $catalogFile ? [
                    'url' => $catalogFile['url'],
                    'name' => $firstCategory->getFirstMedia('catalog_files')?->file_name,
                ] : null,
                'products' => $items->map(fn (EventProduct $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'price' => $currency === 'USD' ? $p->price_usd : $p->price,
                    'unit' => $p->unit,
                    'product_image' => $p->product_image,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped, 'currency' => $currency]);
    }

    /**
     * Get order form info (T&C, settings) for the event.
     */
    public function orderFormInfo(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $event = $brandEvent->event;
        $settings = $event->settings ?? [];
        $currency = $brandEvent->resolveCurrency();

        // Determine current order period
        $now = now();
        $currentPeriod = null;
        $penaltyRate = 0;

        if ($event->normal_order_opens_at && $event->normal_order_closes_at
            && $now->between($event->normal_order_opens_at, $event->normal_order_closes_at)) {
            $currentPeriod = 'normal_order';
        } elseif ($event->onsite_order_opens_at && $event->onsite_order_closes_at
            && $now->between($event->onsite_order_opens_at, $event->onsite_order_closes_at)) {
            $currentPeriod = 'onsite_order';
            $penaltyRate = (float) $event->onsite_penalty_rate;
        } elseif (! $event->normal_order_opens_at && ! $event->onsite_order_opens_at) {
            $currentPeriod = 'normal_order';
        }

        return response()->json([
            'data' => [
                'order_form_content' => $event->order_form_content,
                'currency' => $currency,
                'tax_rate' => $currency === 'USD'
                    ? ($settings['tax_rate_usd'] ?? $settings['tax_rate'] ?? 11)
                    : ($settings['tax_rate'] ?? 11),
                'order_form_deadline' => $event->order_form_deadline?->toIso8601String(),
                'promotion_post_deadline' => $event->promotion_post_deadline?->toIso8601String(),
                'current_period' => $currentPeriod,
                'penalty_rate' => $penaltyRate,
                'brand_event' => [
                    'id' => $brandEvent->id,
                    'booth_number' => $brandEvent->booth_number,
                    'booth_type' => $brandEvent->booth_type?->value,
                    'booth_type_label' => $brandEvent->booth_type?->label(),
                    'fascia_name' => $brandEvent->fascia_name,
                    'badge_name' => $brandEvent->badge_name,
                ],
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'date_label' => $event->date_label,
                ],
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'company_name' => $brand->company_name,
                ],
            ],
        ]);
    }

    /**
     * Submit an order from the exhibitor.
     */
    public function submitOrder(SubmitOrderRequest $request, string $brandSlug, int $brandEventId, OrderSubmissionService $orderSubmission): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $event = $brandEvent->event;

        // Check deadline - only enforce for non-staff users
        if ($event->order_form_deadline && $event->order_form_deadline->isPast() && ! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            return response()->json(['message' => 'Order form deadline has passed.'], 422);
        }

        $validated = $request->validated();

        try {
            $order = $orderSubmission->create($brandEvent, $validated['items'], [
                'notes' => $validated['notes'] ?? null,
                'promo_code' => $validated['promo_code'] ?? null,
                'promo_email' => $brand->email ?? $request->user()->email ?? '',
                'source' => 'exhibitor',
                'user' => $request->user(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.productCategory', 'brandEvent.brand', 'creator']);

        // Send notification emails
        $orderSubmission->sendEmails($order, $event, $brand, $request->user());

        // Notify project members + master/admin about the new order
        $notifiableUsers = $event->project->getNotifiableUsers();
        foreach ($notifiableUsers as $notifiableUser) {
            $notifiableUser->notify(new OrderSubmittedNotification($order, $brand->name));
        }

        return response()->json([
            'message' => 'Order submitted successfully',
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * List orders for the exhibitor's brand event.
     */
    public function myOrders(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $orders = Order::query()
            ->where('brand_event_id', $brandEvent->id)
            ->with(['creator', 'items'])
            ->withCount('items')
            ->orderByDesc('submitted_at')
            ->get();

        return response()->json([
            'data' => OrderIndexResource::collection($orders),
        ]);
    }

    /**
     * Show a specific order for the exhibitor.
     */
    public function myOrderShow(Request $request, string $brandSlug, int $brandEventId, string $ulid): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $order = Order::query()
            ->where('brand_event_id', $brandEvent->id)
            ->where('ulid', $ulid)
            ->with(['items.productCategory', 'creator'])
            ->firstOrFail();

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Get event documents and exhibitor's submissions for a brand-event.
     */
    public function eventDocuments(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $event = $brandEvent->event;

        // Get all documents for this event, filtered by booth type
        $documents = $event->eventDocuments()
            ->with(['media', 'fields'])
            ->ordered()
            ->get()
            ->filter(fn ($doc) => $doc->appliesToBoothType($brandEvent->booth_type?->value))
            ->values();

        // Get exhibitor's submissions for this booth
        $boothIdentifier = $brandEvent->booth_number ?: "be-{$brandEvent->id}";
        $submissions = EventDocumentSubmission::query()
            ->where('event_id', $event->id)
            ->where('booth_identifier', $boothIdentifier)
            ->with(['eventDocument', 'media', 'submitter'])
            ->get()
            ->keyBy('event_document_id');

        $documentsData = $documents->map(function ($doc) use ($submissions) {
            $submission = $submissions->get($doc->id);

            return [
                'document' => new EventDocumentResource($doc),
                'submission' => $submission ? new EventDocumentSubmissionResource($submission) : null,
            ];
        });

        return response()->json([
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'date_label' => $event->date_label,
                ],
                'brand_event' => [
                    'id' => $brandEvent->id,
                    'booth_number' => $brandEvent->booth_number,
                    'booth_type' => $brandEvent->booth_type?->value,
                ],
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ],
                'documents' => $documentsData,
            ],
        ]);
    }

    /**
     * Submit a document mini-form (multi-field answers keyed by field ulid,
     * files via tmp uploads keyed by field ulid). The legacy single-value
     * payload (text_value / tmp_submission_file / implicit agreement) is
     * still accepted for one release and mapped onto the synthesized
     * system_key fields.
     */
    public function submitDocument(Request $request, string $brandSlug, int $brandEventId, string $documentUlid): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $event = $brandEvent->event;
        $document = $event->eventDocuments()->where('ulid', $documentUlid)->firstOrFail();

        // Check booth type applicability
        if (! $document->appliesToBoothType($brandEvent->booth_type?->value)) {
            return response()->json(['message' => 'This document does not apply to your booth type.'], 422);
        }

        // Enforce the submission deadline server-side; staff may still submit on
        // an exhibitor's behalf after it passes (mirrors submitOrder).
        if ($document->isPastDeadline() && ! $request->user()->hasRole(['master', 'admin', 'staff'])) {
            return response()->json(['message' => 'The submission deadline for this document has passed.'], 422);
        }

        $boothIdentifier = $brandEvent->booth_number ?: "be-{$brandEvent->id}";

        $validated = $request->validate([
            'field_values' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'text_value' => ['nullable', 'string', 'max:5000'],
            'tmp_submission_file' => ['nullable', 'string'],
            'agreement' => ['nullable', 'boolean'],
        ]);

        $fields = $document->fields()->active()->get();
        $fileFields = $fields->filter(fn (CustomField $field) => $field->type === CustomField::TYPE_FILE);
        $inputFields = $fields->reject(fn (CustomField $field) => $field->type === CustomField::TYPE_FILE);

        // The agreed_at mirror targets the backfilled agreement checkbox; new
        // builder-made rule documents fall back to their required checkbox.
        $agreementField = $fields->first(fn (CustomField $field) => $field->type === CustomField::TYPE_CHECKBOX && $field->system_key === 'agreement')
            ?? $fields->first(fn (CustomField $field) => $field->type === CustomField::TYPE_CHECKBOX && ! empty($field->validation['required']));
        $legacyTextField = $fields->first(fn (CustomField $field) => $field->type === CustomField::TYPE_TEXTAREA && $field->system_key === 'legacy_text');
        $legacyFileField = $fileFields->first(fn (CustomField $field) => $field->system_key === 'legacy_file');

        $incoming = is_array($validated['field_values'] ?? null) ? $validated['field_values'] : [];
        $incomingFiles = $this->normalizedTmpFileMap(is_array($validated['files'] ?? null) ? $validated['files'] : []);

        // Legacy payload bridge (accepted for one release).
        if ($legacyTextField !== null && $request->exists('text_value') && ! array_key_exists($legacyTextField->ulid, $incoming)) {
            $incoming[$legacyTextField->ulid] = $validated['text_value'] ?? null;
        }

        if ($legacyFileField !== null && ! empty($validated['tmp_submission_file']) && ! isset($incomingFiles[$legacyFileField->ulid])) {
            $incomingFiles += $this->normalizedTmpFileMap([$legacyFileField->ulid => $validated['tmp_submission_file']]);
        }

        if ($agreementField !== null && ! array_key_exists($agreementField->ulid, $incoming)) {
            if ($request->exists('agreement')) {
                $incoming[$agreementField->ulid] = (bool) ($validated['agreement'] ?? false);
            } elseif (! $request->exists('field_values') && $document->document_type === 'checkbox_agreement') {
                // The old portal (re)agrees to a rule document by posting an empty body.
                $incoming[$agreementField->ulid] = true;
            }
        }

        $existing = EventDocumentSubmission::query()
            ->where('event_document_id', $document->id)
            ->where('booth_identifier', $boothIdentifier)
            ->where('event_id', $event->id)
            ->first();

        // Validate the prospective (merged) answers so a partial re-submit does
        // not fail `required` on fields that were already answered before.
        $merged = CustomFieldValues::mergeUlidKeyed($existing?->field_values, $fields, $incoming);

        $errors = CustomFieldValidation::errorsFor($inputFields, $merged, 'field_values', 'ulid');

        foreach ($fileFields as $field) {
            if (empty($field->validation['required'])) {
                continue;
            }

            if (isset($incomingFiles[$field->ulid]) || $this->submissionHasFileForField($existing, $field)) {
                continue;
            }

            $errors['field_values.'.$field->ulid] = 'The '.$field->label.' file is required.';
        }

        // Legacy documents without a mini-form keep the old required-file rule.
        if ($fileFields->isEmpty()
            && $document->document_type === 'file_upload'
            && $document->is_required
            && (! $existing || ! $existing->hasMedia('submission_file'))
            && empty($validated['tmp_submission_file'])) {
            $errors['tmp_submission_file'] = 'The submission file is required.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $submission = $existing ?? new EventDocumentSubmission([
            'event_document_id' => $document->id,
            'booth_identifier' => $boothIdentifier,
            'event_id' => $event->id,
        ]);

        $submission->fill([
            'document_version' => $document->content_version,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Mirror the agreement answer into the legacy agreed_at column so
        // needsReagreement() and the dashboard "agreed" status keep working.
        if ($agreementField !== null) {
            $submission->agreed_at = ! empty($merged[$agreementField->ulid]) ? now() : null;
        } elseif ($fields->isEmpty()) {
            $submission->agreed_at = $document->document_type === 'checkbox_agreement' ? now() : null;
        }

        // Mirror the synthesized textarea into the legacy text_value column.
        if ($legacyTextField !== null && array_key_exists($legacyTextField->ulid, $merged)) {
            $submission->text_value = $merged[$legacyTextField->ulid];
        } elseif ($fields->isEmpty()) {
            $submission->text_value = $validated['text_value'] ?? null;
        }

        $submission->save();

        foreach ($fileFields as $field) {
            $merged = $this->storeSubmissionFieldFiles($submission, $field, $incomingFiles[$field->ulid] ?? [], $merged);
        }

        // Legacy documents without a file field keep the old single-file flow,
        // but still version the file so re-uploads never destroy the old one.
        if ($fileFields->isEmpty() && $request->has('tmp_submission_file')) {
            $tmpId = $request->input('tmp_submission_file');
            if (is_string($tmpId) && Str::startsWith($tmpId, 'tmp-')) {
                $this->storeLegacySubmissionFileVersion($submission, $tmpId);
            }
        }

        $submission->field_values = $merged;
        $submission->save();

        $submission->load(['eventDocument.fields', 'eventDocument.media', 'media', 'submitter']);

        return response()->json([
            'message' => 'Document submitted successfully',
            'data' => new EventDocumentSubmissionResource($submission),
        ]);
    }

    /**
     * Whether an existing submission already holds a file for the given field,
     * counting untagged legacy media for the synthesized legacy_file field.
     */
    private function submissionHasFileForField(?EventDocumentSubmission $submission, CustomField $field): bool
    {
        if ($submission === null) {
            return false;
        }

        if (! empty($submission->field_values[$field->ulid] ?? null)) {
            return true;
        }

        return $submission->currentSubmissionFiles()->contains(
            fn (Media $media) => $media->getCustomProperty('field_ulid') === $field->ulid
                || ($field->system_key === 'legacy_file' && $media->getCustomProperty('field_ulid') === null)
        );
    }

    /**
     * Number of past versions kept per file field (1 current + 4 superseded).
     * Prevents unbounded growth while preserving an audit trail.
     */
    private const FILE_VERSION_RETENTION = 5;

    /**
     * Attach tmp uploads to the submission for one file field. For single-file
     * fields the previous file is kept as a superseded version (audit trail,
     * retention {@see FILE_VERSION_RETENTION}) instead of being deleted; the
     * multiple-file fields keep the accumulate-forever behaviour. Returns the
     * field_values map with the field's CURRENT media ids.
     *
     * @param  array<int, string>  $tmpIds
     * @param  array<string, mixed>  $merged
     * @return array<string, mixed>
     */
    private function storeSubmissionFieldFiles(EventDocumentSubmission $submission, CustomField $field, array $tmpIds, array $merged): array
    {
        if ($tmpIds === []) {
            return $merged;
        }

        $multiple = ! empty($field->settings['multiple']);
        $keptIds = [];

        if ($multiple) {
            $keptIds = $submission->currentFilesForField($field->ulid)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            // Supersede (do not delete) the field's current file(s) so the old
            // version stays in history. Also claim any legacy media that predates
            // per-field tagging.
            $submission->getMedia('submission_file')
                ->filter(fn (Media $media) => $media->getCustomProperty('superseded_at') === null
                    && ($media->getCustomProperty('field_ulid') === $field->ulid
                        || ($field->system_key === 'legacy_file' && $media->getCustomProperty('field_ulid') === null)))
                ->each(function (Media $media) use ($field) {
                    if ($media->getCustomProperty('field_ulid') === null) {
                        $media->setCustomProperty('field_ulid', $field->ulid);
                    }
                    $media->setCustomProperty('superseded_at', now()->toIso8601String());
                    $media->save();
                });

            $tmpIds = array_slice($tmpIds, 0, 1);
        }

        $newIds = [];

        foreach ($tmpIds as $tmpId) {
            $media = $this->addSubmissionFileFromTmp($submission, $field, $tmpId);

            if ($media !== null) {
                $newIds[] = $media->id;
            }
        }

        if (! $multiple) {
            $this->pruneFieldVersions($submission, $field->ulid);
        }

        $merged[$field->ulid] = array_values(array_merge($keptIds, $newIds));

        return $merged;
    }

    /**
     * Keep only the most recent {@see FILE_VERSION_RETENTION} versions of a
     * field's file, deleting older superseded versions.
     */
    private function pruneFieldVersions(EventDocumentSubmission $submission, string $fieldUlid): void
    {
        // Reload media so the just-added version is included; Spatie memoizes the
        // media relation, which would otherwise be stale after addMedia().
        $submission->load('media');

        $submission->fileHistoryForField($fieldUlid)
            ->slice(self::FILE_VERSION_RETENTION)
            ->each(fn (Media $media) => $media->delete());
    }

    private function addSubmissionFileFromTmp(EventDocumentSubmission $submission, CustomField $field, string $tmpId): ?Media
    {
        $metadataPath = "tmp/uploads/{$tmpId}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return null;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpId}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return null;
        }

        $nextVersion = ((int) $submission->fileHistoryForField($field->ulid)
            ->max(fn (Media $media) => (int) $media->getCustomProperty('version', 1))) + 1;

        $media = $submission->addMedia(Storage::disk('local')->path($filePath))
            ->withCustomProperties([
                'field_ulid' => $field->ulid,
                'version' => $nextVersion,
                'uploaded_by' => auth()->id(),
                'uploaded_by_name' => auth()->user()?->name,
            ])
            ->toMediaCollection('submission_file');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpId}");

        return $media;
    }

    /**
     * Version-aware single-file upload for legacy documents that have no
     * mini-form file field. Supersedes the current file (grouped under the
     * 'legacy' history key) instead of deleting it, then prunes old versions.
     */
    private function storeLegacySubmissionFileVersion(EventDocumentSubmission $submission, string $tmpId): void
    {
        $metadataPath = "tmp/uploads/{$tmpId}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpId}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $submission->currentSubmissionFiles()
            ->each(function (Media $media) {
                $media->setCustomProperty('superseded_at', now()->toIso8601String());
                $media->save();
            });

        $nextVersion = ((int) $submission->fileHistoryForField('legacy')
            ->max(fn (Media $media) => (int) $media->getCustomProperty('version', 1))) + 1;

        $submission->addMedia(Storage::disk('local')->path($filePath))
            ->withCustomProperties([
                'version' => $nextVersion,
                'uploaded_by' => auth()->id(),
                'uploaded_by_name' => auth()->user()?->name,
            ])
            ->toMediaCollection('submission_file');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpId}");

        $this->pruneFieldVersions($submission, 'legacy');
    }

    /**
     * Canonicalize the files payload to {field_ulid: [tmp ids]}, dropping
     * anything that is not a tmp-upload reference.
     *
     * @param  array<string, mixed>  $files
     * @return array<string, array<int, string>>
     */
    private function normalizedTmpFileMap(array $files): array
    {
        $map = [];

        foreach ($files as $ulid => $value) {
            $tmpIds = array_values(array_filter(
                is_array($value) ? $value : [$value],
                fn ($id) => is_string($id) && Str::startsWith($id, 'tmp-')
            ));

            if ($tmpIds !== []) {
                $map[(string) $ulid] = $tmpIds;
            }
        }

        return $map;
    }

    /**
     * Update booth fields (fascia_name, badge_name) for a brand-event.
     */
    public function updateBoothFields(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->findOrFail($brandEventId);

        $validated = $request->validate([
            'fascia_name' => ['nullable', 'string', 'max:24'],
            'badge_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Force fascia_name uppercase
        if (! empty($validated['fascia_name'])) {
            $validated['fascia_name'] = strtoupper($validated['fascia_name']);
        }

        $brandEvent->update($validated);

        return response()->json([
            'message' => 'Booth fields updated successfully',
            'data' => [
                'fascia_name' => $brandEvent->fascia_name,
                'badge_name' => $brandEvent->badge_name,
            ],
        ]);
    }

    /**
     * Get order period info for the exhibitor.
     */
    public function orderPeriodInfo(Request $request, string $brandSlug, int $brandEventId): JsonResponse
    {
        $brand = $request->user()->brands()->where('brands.slug', $brandSlug)->firstOrFail();
        $brandEvent = BrandEvent::query()
            ->where('brand_id', $brand->id)
            ->with('event')
            ->findOrFail($brandEventId);

        $event = $brandEvent->event;
        $now = now();

        $currentPeriod = null;
        $canOrder = false;

        if ($event->normal_order_opens_at && $event->normal_order_closes_at) {
            if ($now->between($event->normal_order_opens_at, $event->normal_order_closes_at)) {
                $currentPeriod = 'normal_order';
                $canOrder = true;
            }
        }

        if ($event->onsite_order_opens_at && $event->onsite_order_closes_at) {
            if ($now->between($event->onsite_order_opens_at, $event->onsite_order_closes_at)) {
                $currentPeriod = 'onsite_order';
                $canOrder = true;
            }
        }

        // Fallback: if no periods configured, use legacy deadline check
        if (! $event->normal_order_opens_at && ! $event->onsite_order_opens_at) {
            $canOrder = ! $event->order_form_deadline || ! $event->order_form_deadline->isPast();
            $currentPeriod = $canOrder ? 'normal_order' : null;
        }

        return response()->json([
            'data' => [
                'current_period' => $currentPeriod,
                'can_order' => $canOrder,
                'penalty_rate' => $currentPeriod === 'onsite_order' ? (float) $event->onsite_penalty_rate : 0,
                'normal_order' => [
                    'opens_at' => $event->normal_order_opens_at?->toIso8601String(),
                    'closes_at' => $event->normal_order_closes_at?->toIso8601String(),
                ],
                'onsite_order' => [
                    'opens_at' => $event->onsite_order_opens_at?->toIso8601String(),
                    'closes_at' => $event->onsite_order_closes_at?->toIso8601String(),
                    'penalty_rate' => (float) $event->onsite_penalty_rate,
                ],
            ],
        ]);
    }

    private function handleTemporaryUpload(Request $request, $model, string $fieldName, string $collection): void
    {
        $bustsBrandCache = in_array($collection, ['profile_image', 'brand_logo'], true);

        $deleteFieldName = 'delete_'.str_replace('tmp_', '', $fieldName);
        if ($request->has($deleteFieldName) && $request->input($deleteFieldName) === true) {
            $model->clearMediaCollection($collection);

            // Profile image (avatar) appears on the cached public brand payloads;
            // MediaLibrary does not fire the Brand saved event, so bust manually.
            if ($bustsBrandCache) {
                ResponseCache::clear(['brands']);
            }

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

        // Avatar/logo media is committed after the brand update, so bust again
        // here to avoid repopulating the cache with the old media.
        if ($bustsBrandCache) {
            ResponseCache::clear(['brands']);
        }
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

        // Promotion-post images surface on the public brand previews/detail.
        // MediaLibrary add does not fire the PromotionPost saved event.
        ResponseCache::clear(['brands', 'promotion-posts']);
    }
}
