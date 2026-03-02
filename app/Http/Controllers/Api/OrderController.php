<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Models\BrandEvent;
use App\Models\Event;
use App\Models\Order;
use App\Models\Project;
use App\Notifications\OrderStatusChangedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        if ($status = $request->input('status')) {
            $query->where('status', $status);
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

        if ($request->has('filter.status')) {
            $statuses = explode(',', $request->input('filter.status'));
            $query->whereIn('status', $statuses);
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
            ->with(['items', 'brandEvent.brand', 'creator'])
            ->firstOrFail();

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function updateStatus(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['submitted', 'confirmed', 'processing', 'completed', 'cancelled'])],
        ]);

        $oldStatus = $order->status;

        $order->update([
            'status' => $validated['status'],
            'confirmed_at' => $validated['status'] === 'confirmed' ? now() : $order->confirmed_at,
        ]);

        // Notify the order creator if status changed
        if ($oldStatus !== $validated['status'] && $order->creator) {
            $order->creator->notify(new OrderStatusChangedNotification($order, $validated['status'], $request->user()));
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'data' => new OrderResource($order->load(['items', 'brandEvent.brand', 'creator'])),
        ]);
    }

    public function applyDiscount(Request $request, string $username, string $eventSlug, string $ulid): JsonResponse
    {
        $project = $this->resolveProject($username);
        $event = $this->resolveEvent($project, $eventSlug);

        $order = Order::query()
            ->whereIn('brand_event_id', $event->brandEvents()->select('id'))
            ->where('ulid', $ulid)
            ->firstOrFail();

        $validated = $request->validate([
            'discount_type' => ['nullable', 'string', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Additional validation
        if (($validated['discount_type'] ?? null) === 'percentage' && ($validated['discount_value'] ?? 0) > 100) {
            return response()->json(['message' => 'Percentage discount cannot exceed 100%.'], 422);
        }

        if (($validated['discount_type'] ?? null) === 'fixed' && ($validated['discount_value'] ?? 0) > (float) $order->subtotal) {
            return response()->json(['message' => 'Fixed discount cannot exceed subtotal.'], 422);
        }

        // Clear discount if type is null
        if (empty($validated['discount_type'])) {
            $order->discount_type = null;
            $order->discount_value = null;
            $order->discount_amount = null;
            // Recalculate without discount
            $subtotal = (float) $order->subtotal;
            $order->tax_amount = round($subtotal * (float) $order->tax_rate / 100, 2);
            $order->total = $subtotal + (float) $order->tax_amount;
        } else {
            $order->discount_type = $validated['discount_type'];
            $order->discount_value = $validated['discount_value'] ?? 0;
            $order->recalculateTotal();
        }

        $order->save();

        return response()->json([
            'message' => 'Discount applied successfully',
            'data' => new OrderResource($order->load(['items', 'brandEvent.brand', 'creator'])),
        ]);
    }
}
