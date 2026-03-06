<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitOrderRequest;
use App\Http\Resources\EventDocumentResource;
use App\Http\Resources\EventDocumentSubmissionResource;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PromotionPostResource;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderSubmittedMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\EventDocumentSubmission;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\PromotionPost;
use App\Models\User;
use App\Notifications\OrderSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
                    $q2->with('media')->ordered();
                }])
                    ->withCount(['promotionPosts', 'orders']);
            },
        ])->get();

        // Collect all brand-event IDs to batch-load submissions
        $allBrandEvents = $brands->flatMap(fn (Brand $b) => $b->brandEvents);
        $boothIdentifiers = $allBrandEvents->map(fn (BrandEvent $be) => $be->booth_number ?? "be-{$be->id}");
        $eventIds = $allBrandEvents->pluck('event_id')->unique();

        // Batch load all submissions for these events + booths
        $allSubmissions = EventDocumentSubmission::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('booth_identifier', $boothIdentifiers)
            ->with(['media', 'submitter'])
            ->get()
            ->groupBy(fn ($s) => "{$s->event_id}:{$s->booth_identifier}:{$s->event_document_id}");

        $brandEventsData = $brands->flatMap(function (Brand $brand) use ($allSubmissions) {
            $brandMissingFields = collect([
                'company_name' => 'Company Name',
                'company_email' => 'Email',
                'company_phone' => 'Phone',
                'company_address' => 'Address',
                'description' => 'Description',
            ])->filter(fn ($label, $field) => empty($brand->{$field}))->values()->all();

            return $brand->brandEvents->map(function (BrandEvent $be) use ($brand, $brandMissingFields, $allSubmissions) {
                $event = $be->event;
                if (! $event) {
                    return null;
                }

                $boothIdentifier = $be->booth_number ?? "be-{$be->id}";
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
                        $needsReagreement = $submission->document_version < $doc->content_version;
                        if ($needsReagreement) {
                            $status = 'needs_reagreement';
                        } elseif ($doc->document_type === 'checkbox_agreement' && $submission->agreed_at) {
                            $status = 'completed';
                        } elseif ($doc->document_type === 'file_upload' && $submission->hasMedia('submission_file')) {
                            $status = 'completed';
                        } elseif ($doc->document_type === 'text_input' && $submission->text_value) {
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
                \Spatie\Tags\Tag::withType("business_category:{$projectId}")
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
                'company_address' => $brand->company_address,
                'company_email' => $brand->company_email,
                'company_phone' => $brand->company_phone,
                'custom_fields' => $brand->custom_fields,
                'status' => $brand->status,
                'visibility' => $brand->visibility,
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
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'custom_fields' => ['nullable', 'array'],
            'business_categories' => ['nullable', 'array'],
            'business_categories.*' => ['string', 'max:100'],
            'tmp_brand_logo' => ['nullable', 'string'],
            'delete_brand_logo' => ['nullable', 'boolean'],
        ]);

        $categories = $validated['business_categories'] ?? null;
        unset($validated['business_categories'], $validated['tmp_brand_logo'], $validated['delete_brand_logo']);

        $brand->update($validated);

        if ($categories !== null) {
            $brand->syncBusinessCategories($categories);
        }

        $this->handleTemporaryUpload($request, $brand, 'tmp_brand_logo', 'brand_logo');

        $brand->load('media');

        return response()->json([
            'message' => 'Brand updated successfully',
            'data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
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

        \Spatie\MediaLibrary\MediaCollections\Models\Media::setNewOrder($validated['media_ids']);

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

        $query = EventProduct::query()
            ->with(['media', 'productCategory'])
            ->where('event_id', $brandEvent->event_id)
            ->where('is_active', true)
            ->ordered();

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
        $grouped = $products->groupBy(fn ($p) => $p->productCategory?->title ?? 'Uncategorized')->map(fn ($items, $category) => [
            'category' => $category,
            'products' => $items->map(fn (EventProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price,
                'unit' => $p->unit,
                'product_image' => $p->product_image,
            ])->values(),
        ])->values();

        return response()->json(['data' => $grouped]);
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
                'tax_rate' => $settings['tax_rate'] ?? 11,
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
    public function submitOrder(SubmitOrderRequest $request, string $brandSlug, int $brandEventId): JsonResponse
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

        $settings = $event->settings ?? [];
        $taxRate = (float) ($settings['tax_rate'] ?? 11);

        $validated = $request->validated();

        // Load and validate products
        $productIds = collect($validated['items'])->pluck('event_product_id');
        $products = EventProduct::query()
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->unique()->count()) {
            return response()->json(['message' => 'Some products are no longer available.'], 422);
        }

        $products->loadMissing('media');

        // Determine current order period and penalty
        $now = now();
        $orderPeriod = 'normal_order';
        $penaltyRate = 0;

        if ($event->normal_order_opens_at && $event->normal_order_closes_at
            && $now->between($event->normal_order_opens_at, $event->normal_order_closes_at)) {
            $orderPeriod = 'normal_order';
        } elseif ($event->onsite_order_opens_at && $event->onsite_order_closes_at
            && $now->between($event->onsite_order_opens_at, $event->onsite_order_closes_at)) {
            $orderPeriod = 'onsite_order';
            $penaltyRate = (float) $event->onsite_penalty_rate;
        }

        $order = DB::transaction(function () use ($validated, $brandEvent, $products, $taxRate, $orderPeriod, $penaltyRate) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products[$item['event_product_id']];
                $totalPrice = (float) $product->price * $item['quantity'];
                $subtotal += $totalPrice;

                $itemsData[] = [
                    'event_product_id' => $product->id,
                    'category_id' => $product->category_id,
                    'product_name' => $product->name,
                    'product_image_url' => $product->product_image['md'] ?? $product->product_image['url'] ?? null,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // Apply penalty if onsite
            $penaltyAmount = $penaltyRate > 0 ? round($subtotal * $penaltyRate / 100, 2) : 0;
            $subtotalWithPenalty = $subtotal + $penaltyAmount;

            $taxAmount = round($subtotalWithPenalty * $taxRate / 100, 2);
            $total = $subtotalWithPenalty + $taxAmount;

            $order = Order::create([
                'brand_event_id' => $brandEvent->id,
                'operational_status' => 'submitted',
                'order_period' => $orderPeriod,
                'applied_penalty_rate' => $penaltyRate,
                'notes' => $validated['notes'] ?? null,
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'submitted_at' => now(),
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        $order->load(['items.productCategory', 'brandEvent.brand', 'creator']);

        // Send notification emails
        $this->sendOrderEmails($order, $event, $brand, $request->user());

        // Notify staff+ users about the new order
        $staffUsers = User::role(['master', 'admin', 'staff'])->get();
        foreach ($staffUsers as $staffUser) {
            $staffUser->notify(new OrderSubmittedNotification($order, $brand->name));
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
            ->with('creator')
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
            ->with('media')
            ->ordered()
            ->get()
            ->filter(fn ($doc) => $doc->appliesToBoothType($brandEvent->booth_type?->value))
            ->values();

        // Get exhibitor's submissions for this booth
        $boothIdentifier = $brandEvent->booth_number ?? "be-{$brandEvent->id}";
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
     * Submit/agree to an event document.
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

        $boothIdentifier = $brandEvent->booth_number ?? "be-{$brandEvent->id}";

        $rules = [
            'text_value' => ['nullable', 'string', 'max:5000'],
            'tmp_submission_file' => ['nullable', 'string'],
        ];

        if ($document->document_type === 'file_upload' && $document->is_required) {
            // Check if there's already a submission with a file
            $existingSubmission = EventDocumentSubmission::query()
                ->where('event_document_id', $document->id)
                ->where('booth_identifier', $boothIdentifier)
                ->where('event_id', $event->id)
                ->first();

            if (! $existingSubmission || ! $existingSubmission->hasMedia('submission_file')) {
                $rules['tmp_submission_file'] = ['required', 'string'];
            }
        }

        $validated = $request->validate($rules);

        // Upsert submission
        $submission = EventDocumentSubmission::updateOrCreate(
            [
                'event_document_id' => $document->id,
                'booth_identifier' => $boothIdentifier,
                'event_id' => $event->id,
            ],
            [
                'agreed_at' => $document->document_type === 'checkbox_agreement' ? now() : null,
                'text_value' => $validated['text_value'] ?? null,
                'document_version' => $document->content_version,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        // Handle file upload
        if ($request->has('tmp_submission_file')) {
            $this->handleTemporaryUpload($request, $submission, 'tmp_submission_file', 'submission_file');
        }

        $submission->load(['eventDocument', 'media', 'submitter']);

        return response()->json([
            'message' => 'Document submitted successfully',
            'data' => new EventDocumentSubmissionResource($submission),
        ]);
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
            'fascia_name' => ['nullable', 'string', 'max:255'],
            'badge_name' => ['nullable', 'string', 'max:255'],
        ]);

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

    /**
     * Send order notification emails.
     */
    private function sendOrderEmails(Order $order, $event, Brand $brand, $user): void
    {
        try {
            $settings = $event->settings ?? [];
            $notificationEmails = $settings['notification_emails'] ?? [];

            // Send to operational/notification emails
            if (! empty($notificationEmails)) {
                foreach ($notificationEmails as $email) {
                    Mail::to($email)->queue(new OrderSubmittedMail($order, $event, $brand));
                }
            }

            // Send confirmation to exhibitor
            if ($user->email) {
                Mail::to($user->email)->queue(new OrderConfirmationMail($order, $event, $brand));
            }
        } catch (\Exception $e) {
            logger()->warning('Failed to send order emails', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

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
}
