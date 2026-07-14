<?php

namespace App\Http\Controllers\Api;

use App\Enums\OperationalStatus;
use App\Enums\PaymentStatus;
use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreManualOrderRequest;
use App\Http\Requests\Order\UpdateOrderInternalNotesRequest;
use App\Http\Requests\Order\UploadOrderInvoiceRequest;
use App\Http\Requests\Order\UploadOrderReceiptRequest;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Jobs\Order\SendOrderDocumentJob;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\EventProduct;
use App\Models\Order;
use App\Models\Project;
use App\Notifications\OrderStatusChangedNotification;
use App\Notifications\OrderSubmittedNotification;
use App\Services\Order\OrderSubmissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all orders. Staff+ see all, exhibitors see only their brands' orders.
     */
    public function all(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::query()
            ->with(['brandEvent.brand', 'brandEvent.event.project', 'creator'])
            ->withCount('items');

        // Exhibitors only see orders for their brands
        if (! $user->hasRole(['master', 'admin', 'staff'])) {
            $query->whereIn('brand_event_id',
                BrandEvent::query()
                    ->whereIn('brand_id', $user->brands()->select('brands.id'))
                    ->select('id')
            );
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'ilike', "%{$search}%")
                    ->orWhereHas('brandEvent.brand', function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($status = $request->input('operational_status')) {
            $query->where('operational_status', $status);
        }

        if ($paymentStatus = $request->input('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if (in_array($currency = $request->input('currency'), ['IDR', 'USD'], true)) {
            $query->where('currency', $currency);
        }

        if ($request->filled('total_min')) {
            $query->where('total_idr', '>=', (float) $request->input('total_min'));
        }

        if ($request->filled('total_max')) {
            $query->where('total_idr', '<=', (float) $request->input('total_max'));
        }

        if ($request->boolean('client_only')) {
            $orders = $query->orderByDesc('submitted_at')->get();

            return response()->json(['data' => OrderIndexResource::collection($orders)]);
        }

        [$sortColumn, $sortDirection] = $this->resolveOrderSort($request->input('sort', '-submitted_at'));
        $query->orderBy($sortColumn, $sortDirection);

        $orders = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => OrderIndexResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    private function resolveProject(string $username): Project
    {
        return Project::where('username', $username)->firstOrFail();
    }

    private function resolveEvent(Project $project, string $eventSlug): Event
    {
        return $project->events()->where('slug', $eventSlug)->firstOrFail();
    }

    /**
     * Resolve a client sort string to a whitelisted [column, direction] pair.
     * Reporting sorts on money always target the IDR-normalised column.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveOrderSort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        $columns = [
            'submitted_at' => 'submitted_at',
            'order_number' => 'order_number',
            'total' => 'total_idr',
            'total_idr' => 'total_idr',
            'operational_status' => 'operational_status',
            'payment_status' => 'payment_status',
            'confirmed_at' => 'confirmed_at',
            'created_at' => 'created_at',
        ];

        return [$columns[$field] ?? 'submitted_at', $direction];
    }

    public function index(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $query = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->with(['brandEvent.brand', 'creator']);

        if ($request->has('filter.search')) {
            $search = $request->input('filter.search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'ilike', "%{$search}%")
                    ->orWhereHas('brandEvent.brand', function ($q) use ($search) {
                        $q->where('name', 'ilike', "%{$search}%")
                            ->orWhere('company_name', 'ilike', "%{$search}%");
                    });
            });
        }

        if ($request->has('filter.operational_status')) {
            $statuses = explode(',', $request->input('filter.operational_status'));
            $query->whereIn('operational_status', $statuses);
        }

        if ($request->has('filter.payment_status')) {
            $paymentStatuses = explode(',', $request->input('filter.payment_status'));
            $query->whereIn('payment_status', $paymentStatuses);
        }

        if ($request->has('filter.currency')) {
            $query->whereIn('currency', explode(',', $request->input('filter.currency')));
        }

        if ($request->filled('filter.total_min')) {
            $query->where('total_idr', '>=', (float) $request->input('filter.total_min'));
        }

        if ($request->filled('filter.total_max')) {
            $query->where('total_idr', '<=', (float) $request->input('filter.total_max'));
        }

        [$sortColumn, $sortDirection] = $this->resolveOrderSort($request->input('sort', '-submitted_at'));
        $query->orderBy($sortColumn, $sortDirection);

        $orders = $query->withCount('items')->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => OrderIndexResource::collection($orders->items()),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Catalog + pricing context for the manual order form. Staff pick any brand
     * participating in the event, so BrandEvent is resolved from the event (not
     * the acting user's own brands).
     */
    public function createInfo(Request $request, string $username, string $eventSlug): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->where('event_id', $event->id)
            ->with('brand.media')
            ->findOrFail($request->integer('brand_event_id'));

        $currency = $brandEvent->resolveCurrency();

        $products = EventProduct::query()
            ->with(['media', 'productCategory.media'])
            ->where('event_id', $event->id)
            ->where('is_active', true)
            ->ordered()
            ->when($brandEvent->booth_type, function ($q) use ($brandEvent) {
                $boothType = $brandEvent->booth_type->value;
                $q->where(function ($sub) use ($boothType) {
                    $sub->whereNull('booth_types')
                        ->orWhereJsonContains('booth_types', $boothType);
                });
            })
            ->when($currency === 'USD', fn ($q) => $q->whereNotNull('price_usd'))
            ->get();

        $productsByCategory = $products
            ->groupBy(fn ($p) => $p->productCategory?->title ?? 'Uncategorized')
            ->map(fn ($items, $category) => [
                'category' => $category,
                'products' => $items->map(fn (EventProduct $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'price' => $currency === 'USD' ? $p->price_usd : $p->price,
                    'unit' => $p->unit,
                    'product_image' => $p->product_image,
                ])->values(),
            ])->values();

        $settings = $event->settings ?? [];
        $now = now();
        $currentPeriod = 'normal_order';
        $penaltyRate = 0;

        if ($event->normal_order_opens_at && $event->normal_order_closes_at
            && $now->between($event->normal_order_opens_at, $event->normal_order_closes_at)) {
            $currentPeriod = 'normal_order';
        } elseif ($event->onsite_order_opens_at && $event->onsite_order_closes_at
            && $now->between($event->onsite_order_opens_at, $event->onsite_order_closes_at)) {
            $currentPeriod = 'onsite_order';
            $penaltyRate = (float) $event->onsite_penalty_rate;
        }

        return response()->json([
            'data' => [
                'products_by_category' => $productsByCategory,
                'currency' => $currency,
                'tax_rate' => $currency === 'USD'
                    ? ($settings['tax_rate_usd'] ?? $settings['tax_rate'] ?? 11)
                    : ($settings['tax_rate'] ?? 11),
                'current_period' => $currentPeriod,
                'penalty_rate' => $penaltyRate,
                'order_form_content' => $event->order_form_content,
                'brand_event' => [
                    'id' => $brandEvent->id,
                    'booth_number' => $brandEvent->booth_number,
                    'booth_type' => $brandEvent->booth_type?->value,
                    'booth_type_label' => $brandEvent->booth_type?->label(),
                    'brand' => [
                        'id' => $brandEvent->brand->id,
                        'name' => $brandEvent->brand->name,
                        'profile_image' => $brandEvent->brand->profile_image,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create an order on behalf of an exhibitor (staff manual order). Bypasses
     * the order-form deadline; onsite penalties still apply automatically and
     * can be voided afterwards via the adjustment tools.
     */
    public function store(StoreManualOrderRequest $request, string $username, string $eventSlug, OrderSubmissionService $orderSubmission): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $brandEvent = BrandEvent::query()
            ->where('event_id', $event->id)
            ->with(['brand', 'event.project'])
            ->findOrFail($request->integer('brand_event_id'));

        $validated = $request->validated();

        try {
            $order = $orderSubmission->create($brandEvent, $validated['items'], [
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'promo_code' => $validated['promo_code'] ?? null,
                'promo_email' => $brandEvent->brand->email ?? '',
                'source' => 'staff',
                'user' => $request->user(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->load(['items.productCategory', 'brandEvent.brand', 'creator']);

        // Confirmation to the exhibitor is opt-out (default on); the internal
        // "order submitted" notification email is skipped because staff placed it.
        $orderSubmission->sendEmails(
            $order,
            $event,
            $brandEvent->brand,
            $request->user(),
            notifyInternal: false,
            confirmationToBrand: $request->boolean('send_confirmation_email', true),
        );

        // In-app notification to project members still fires.
        foreach ($event->project->getNotifiableUsers() as $notifiableUser) {
            $notifiableUser->notify(new OrderSubmittedNotification($order, $brandEvent->brand->name));
        }

        return response()->json([
            'message' => 'Order created successfully',
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->with([
                'items.productCategory',
                'brandEvent.brand.media',
                'creator',
                'adjustments.promotionRule',
                'adjustments.promoCode',
                'media',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function updateOperationalStatus(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $user = $request->user();

        // Permission check: master, admin, or staff with operational/project-coordinator
        if (! $user->hasRole(['master', 'admin'])) {
            if (! $user->hasRole('staff') || ! $user->hasAnyPermission(['operational', 'project-coordinator'])) {
                return response()->json(['message' => 'Unauthorized to update operational status.'], 403);
            }
        }

        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        $validated = $request->validate([
            'operational_status' => ['required', 'string', Rule::in(array_column(OperationalStatus::cases(), 'value'))],
            'cancellation_reason' => [$request->input('operational_status') === 'cancelled' ? 'required' : 'nullable', 'string', 'max:5000'],
        ]);

        $oldStatus = $order->operational_status;

        $updateData = [
            'operational_status' => $validated['operational_status'],
            'confirmed_at' => $validated['operational_status'] === 'confirmed' ? now() : $order->confirmed_at,
        ];

        if ($validated['operational_status'] === 'cancelled' && ! empty($validated['cancellation_reason'] ?? null)) {
            $updateData['cancellation_reason'] = $validated['cancellation_reason'];
        }

        $order->update($updateData);

        // Notify the order creator if status changed
        if ($oldStatus?->value !== $validated['operational_status'] && $order->creator) {
            $order->creator->notify(new OrderStatusChangedNotification($order, $validated['operational_status'], $user));
        }

        return response()->json([
            'message' => 'Operational status updated successfully',
            'data' => new OrderResource($order->load(['items.productCategory', 'brandEvent.brand.media', 'creator', 'adjustments.promotionRule', 'adjustments.promoCode'])),
        ]);
    }

    public function updatePaymentStatus(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $user = $request->user();

        // Permission check: master, admin, or staff with finance
        if (! $user->hasRole(['master', 'admin'])) {
            if (! $user->hasRole('staff') || ! $user->hasPermissionTo('finance')) {
                return response()->json(['message' => 'Unauthorized to update payment status.'], 403);
            }
        }

        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        $validated = $request->validate([
            'payment_status' => ['required', 'string', Rule::in(array_column(PaymentStatus::cases(), 'value'))],
        ]);

        $order->update([
            'payment_status' => $validated['payment_status'],
        ]);

        return response()->json([
            'message' => 'Payment status updated successfully',
            'data' => new OrderResource($order->load(['items.productCategory', 'brandEvent.brand.media', 'creator', 'adjustments.promotionRule', 'adjustments.promoCode'])),
        ]);
    }

    public function destroy(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['master', 'admin'])) {
            if (! $user->hasRole('staff') || ! $user->hasAnyPermission(['operational', 'project-coordinator'])) {
                return response()->json(['message' => 'Unauthorized to delete order.'], 403);
            }
        }

        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    public function export(Request $request, string $username, string $eventSlug)
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $filters = array_filter([
            'search' => $request->input('filter_search'),
            'operational_status' => $request->input('filter_operational_status'),
            'payment_status' => $request->input('filter_payment_status'),
            'currency' => $request->input('filter_currency'),
            'total_min' => $request->input('filter_total_min'),
            'total_max' => $request->input('filter_total_max'),
        ]);

        $sort = $request->input('sort', '-submitted_at');

        $export = new OrdersExport($event->id, $filters ?: null, $sort);
        $filename = 'orders_'.now()->format('Y-m-d_His').'.xlsx';

        activity()
            ->causedBy($request->user())
            ->event('exported')
            ->withProperties([
                'project_id' => $event->project_id,
                'model_type' => 'Order',
                'event_id' => $event->id,
                'filename' => $filename,
            ])
            ->log('Exported orders');

        return Excel::download($export, $filename);
    }

    /**
     * Update staff-only internal notes on the order and/or individual items.
     */
    public function updateInternalNotes(UpdateOrderInternalNotesRequest $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $this->ensureCanManageOperational($request);

        [, $order] = $this->resolveOrder($username, $eventSlug, $ulid);

        $data = $request->validated();

        if (array_key_exists('internal_notes', $data)) {
            $order->update(['internal_notes' => $data['internal_notes']]);
        }

        if (! empty($data['items'])) {
            foreach ($data['items'] as $itemData) {
                $order->items()
                    ->whereKey($itemData['id'])
                    ->update(['internal_notes' => $itemData['internal_notes'] ?? null]);
            }
        }

        return response()->json([
            'message' => 'Internal notes updated successfully',
            'data' => new OrderResource($order->fresh([
                'items.productCategory', 'brandEvent.brand.media', 'creator',
                'adjustments.promotionRule', 'adjustments.promoCode', 'media',
            ])),
        ]);
    }

    public function uploadInvoice(UploadOrderInvoiceRequest $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $this->ensureCanManageDocuments($request);

        [, $order] = $this->resolveOrder($username, $eventSlug, $ulid);

        $this->storeDocument($order, 'invoice', $request->input('tmp_invoice'));

        return $this->documentResponse($order, 'invoice', 'Invoice uploaded successfully');
    }

    public function uploadReceipt(UploadOrderReceiptRequest $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $this->ensureCanManageDocuments($request);

        [, $order] = $this->resolveOrder($username, $eventSlug, $ulid);

        $this->storeDocument($order, 'receipt', $request->input('tmp_receipt'));

        return $this->documentResponse($order, 'receipt', 'Receipt uploaded successfully');
    }

    public function sendInvoice(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $this->ensureCanManageDocuments($request);

        [$event, $order] = $this->resolveOrder($username, $eventSlug, $ulid);

        return $this->dispatchDocumentEmail($request, $event, $order, 'invoice');
    }

    public function sendReceipt(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $this->ensureCanManageDocuments($request);

        [$event, $order] = $this->resolveOrder($username, $eventSlug, $ulid);

        return $this->dispatchDocumentEmail($request, $event, $order, 'receipt');
    }

    /**
     * @return array{0: Event, 1: Order}
     */
    private function resolveOrder(string $username, string $eventSlug, string $ulid): array
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        return [$event, $order];
    }

    private function ensureCanManageOperational(Request $request): void
    {
        $user = $request->user();

        if (! $user->hasRole(['master', 'admin'])) {
            if (! $user->hasRole('staff') || ! $user->hasAnyPermission(['operational', 'project-coordinator'])) {
                abort(403, 'Unauthorized to manage this order.');
            }
        }
    }

    private function ensureCanManageDocuments(Request $request): void
    {
        $user = $request->user();

        if (! $user->hasRole(['master', 'admin'])) {
            if (! $user->hasRole('staff') || ! $user->hasPermissionTo('finance')) {
                abort(403, 'Unauthorized to manage order documents.');
            }
        }
    }

    private function storeDocument(Order $order, string $collection, ?string $tmpFolder): void
    {
        if (is_string($tmpFolder) && Str::startsWith($tmpFolder, 'tmp-')) {
            $this->attachTempDocument($order, $collection, $tmpFolder);

            return;
        }

        $order->clearMediaCollection($collection);
        $order->addMediaFromRequest($collection)->toMediaCollection($collection);
    }

    private function attachTempDocument(Order $order, string $collection, string $tmpFolder): void
    {
        $metadataPath = "tmp/uploads/{$tmpFolder}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            abort(422, 'Temporary file not found.');
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpFolder}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            abort(422, 'Temporary file is missing.');
        }

        $order->clearMediaCollection($collection);
        $order->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection($collection);

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpFolder}");
    }

    private function documentResponse(Order $order, string $collection, string $message): JsonResponse
    {
        $media = $order->getFirstMedia($collection);

        return response()->json([
            'message' => $message,
            $collection => [
                'name' => $media?->name,
                'url' => $order->getFirstMediaUrl($collection),
            ],
        ]);
    }

    /**
     * @param  'invoice'|'receipt'  $type
     */
    private function dispatchDocumentEmail(Request $request, Event $event, Order $order, string $type): JsonResponse
    {
        if (! $order->hasMedia($type)) {
            return response()->json(['message' => ucfirst($type).' must be uploaded first'], 422);
        }

        // Throttle to one send per minute per order to prevent spamming.
        $rateLimitKey = "send-order-{$type}:{$order->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => "Please wait {$seconds} seconds before resending.",
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        SendOrderDocumentJob::dispatch($order->id, $type);

        activity()
            ->causedBy($request->user())
            ->performedOn($order)
            ->event("{$type}_sent")
            ->withProperties([
                'project_id' => $event->project_id,
                'order_id' => $order->id,
            ])
            ->log(ucfirst($type).' sent to exhibitor');

        return response()->json(['message' => ucfirst($type).' email sent']);
    }
}
