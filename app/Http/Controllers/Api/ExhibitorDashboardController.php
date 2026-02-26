<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitOrderRequest;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PromotionPostResource;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderSubmittedMail;
use App\Models\Brand;
use App\Models\BrandEvent;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\PromotionPost;
use App\Models\User;
use App\Notifications\OrderSubmittedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExhibitorDashboardController extends Controller
{
    /**
     * Exhibitor dashboard — profile, brands with completeness, upcoming events.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $brands = $user->brands()->with(['media', 'brandEvents.event.media', 'brandEvents.promotionPosts'])->get();

        $profileComplete = ! empty($user->name) && ! empty($user->phone) && ! empty($user->title) && ! empty($user->company_name);

        $brandsData = $brands->map(function (Brand $brand) {
            $missingFields = collect([
                'company_name' => 'Company Name',
                'company_email' => 'Email',
                'company_phone' => 'Phone',
                'company_address' => 'Address',
                'description' => 'Description',
            ])->filter(fn ($label, $field) => empty($brand->{$field}))->values()->all();

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'company_name' => $brand->company_name,
                'brand_logo' => $brand->brand_logo,
                'status' => $brand->status,
                'events_count' => $brand->brandEvents->count(),
                'is_complete' => empty($missingFields),
                'missing_fields' => $missingFields,
            ];
        });

        $upcomingBrandEvents = $brands->flatMap(function (Brand $brand) {
            return $brand->brandEvents
                ->filter(fn (BrandEvent $be) => $be->event && $be->event->end_date && $be->event->end_date->gte(Carbon::today()))
                ->map(fn (BrandEvent $be) => [
                    'brand_event_id' => $be->id,
                    'brand' => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'slug' => $brand->slug,
                        'logo' => $brand->brand_logo,
                    ],
                    'event' => [
                        'id' => $be->event->id,
                        'title' => $be->event->title,
                        'slug' => $be->event->slug,
                        'date_label' => $be->event->date_label,
                        'location' => $be->event->location,
                        'poster_image' => $be->event->poster_image,
                        'order_form_deadline' => $be->event->order_form_deadline?->toIso8601String(),
                        'promotion_post_deadline' => $be->event->promotion_post_deadline?->toIso8601String(),
                    ],
                    'promotion_posts_count' => $be->promotionPosts->count(),
                    'promotion_post_limit' => $be->promotion_post_limit,
                ]);
        })->values();

        // Recent orders (last 5)
        $brandEventIds = $brands->flatMap(fn (Brand $b) => $b->brandEvents->pluck('id'));
        $recentOrders = Order::query()
            ->whereIn('brand_event_id', $brandEventIds)
            ->with(['brandEvent.brand', 'brandEvent.event'])
            ->withCount('items')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

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
                'brands' => $brandsData,
                'upcoming_brand_events' => $upcomingBrandEvents,
                'recent_orders' => OrderIndexResource::collection($recentOrders),
            ],
        ]);
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
            ->with(['media', 'tags', 'links'])
            ->where('brands.slug', $brandSlug)
            ->firstOrFail();

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
            ->with(['event.media', 'promotionPosts.media'])
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
                'promotion_posts_count' => $be->promotionPosts->count(),
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
            ->with('media')
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
        $grouped = $products->groupBy('category')->map(fn ($items, $category) => [
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

        return response()->json([
            'data' => [
                'order_form_content' => $event->order_form_content,
                'tax_rate' => $settings['tax_rate'] ?? 11,
                'order_form_deadline' => $event->order_form_deadline?->toIso8601String(),
                'promotion_post_deadline' => $event->promotion_post_deadline?->toIso8601String(),
                'brand_event' => [
                    'id' => $brandEvent->id,
                    'booth_number' => $brandEvent->booth_number,
                    'booth_type' => $brandEvent->booth_type?->value,
                    'booth_type_label' => $brandEvent->booth_type?->label(),
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

        $order = DB::transaction(function () use ($validated, $brandEvent, $products, $taxRate) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products[$item['event_product_id']];
                $totalPrice = (float) $product->price * $item['quantity'];
                $subtotal += $totalPrice;

                $itemsData[] = [
                    'event_product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_category' => $product->category,
                    'product_image_url' => $product->product_image['md'] ?? $product->product_image['url'] ?? null,
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'total_price' => $totalPrice,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total = $subtotal + $taxAmount;

            $order = Order::create([
                'brand_event_id' => $brandEvent->id,
                'status' => 'submitted',
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

        $order->load(['items', 'brandEvent.brand', 'creator']);

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
            ->with(['items', 'creator'])
            ->firstOrFail();

        return response()->json([
            'data' => new OrderResource($order),
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
