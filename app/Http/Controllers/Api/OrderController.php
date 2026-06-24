<?php

namespace App\Http\Controllers\Api;

use App\Enums\OperationalStatus;
use App\Enums\PaymentStatus;
use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderInternalNotesRequest;
use App\Http\Requests\Order\UploadOrderInvoiceRequest;
use App\Http\Requests\Order\UploadOrderReceiptRequest;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Jobs\Order\SendOrderDocumentJob;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use App\Notifications\OrderStatusChangedNotification;
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

        if ($request->boolean('client_only')) {
            $orders = $query->orderByDesc('submitted_at')->get();

            return response()->json(['data' => OrderIndexResource::collection($orders)]);
        }

        $sort = $request->input('sort', '-submitted_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy($field, $direction);

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

        $sort = $request->input('sort', '-submitted_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        $query->orderBy($field, $direction);

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
