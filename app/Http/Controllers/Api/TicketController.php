<?php

namespace App\Http\Controllers\Api;

use App\Exports\BulkTicketBatchExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\BulkGenerateTicketRequest;
use App\Http\Requests\Ticket\StoreTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketRequest;
use App\Http\Resources\AttendeeResource;
use App\Http\Resources\TicketIndexResource;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\ResponseCache\Facades\ResponseCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function index(Request $request, Event $event): JsonResponse
    {
        $query = $event->tickets()
            ->with(['media'])
            ->withCount(['pricePhases', 'sessions']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('title', $likeOperator, "%{$search}%")
                    ->orWhere('tier', $likeOperator, "%{$search}%")
                    ->orWhere('slug', $likeOperator, "%{$search}%");
            });
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $tickets = $query->orderBy('order_column')->get();

        // The list returns every ticket in one page (events rarely have many),
        // so report a single-page paginator shape - this gives the table a
        // correct "Showing 1 to N of N" range instead of NaN.
        $total = $tickets->count();

        return response()->json([
            'data' => TicketIndexResource::collection($tickets),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => max($total, 1),
                'from' => $total > 0 ? 1 : 0,
                'to' => $total,
                'total' => $total,
            ],
        ]);
    }

    public function store(StoreTicketRequest $request, Event $event): JsonResponse
    {
        $validated = $request->validated();
        $validDays = $validated['valid_days'] ?? [];
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $deletePoster = $validated['delete_poster'] ?? false;

        unset($validated['valid_days'], $validated['tmp_poster'], $validated['delete_poster']);

        $ticket = $event->tickets()->create($validated);

        $ticket->validDays()->sync($validDays);
        $this->handlePosterUpload($tmpPoster, $deletePoster, $ticket);

        return response()->json([
            'message' => 'Ticket created successfully',
            'data' => new TicketResource($this->loadDetail($ticket)),
        ], 201);
    }

    public function show(Event $event, Ticket $ticket): JsonResponse
    {
        return response()->json([
            'data' => new TicketResource($this->loadDetail($ticket)),
        ]);
    }

    public function update(UpdateTicketRequest $request, Event $event, Ticket $ticket): JsonResponse
    {
        $validated = $request->validated();
        $validDays = $validated['valid_days'] ?? null;
        $tmpPoster = $validated['tmp_poster'] ?? null;
        $deletePoster = $validated['delete_poster'] ?? false;

        unset($validated['valid_days'], $validated['tmp_poster'], $validated['delete_poster']);

        $ticket->update($validated);

        if ($validDays !== null) {
            $ticket->validDays()->sync($validDays);
        }

        $this->handlePosterUpload($tmpPoster, $deletePoster, $ticket);

        return response()->json([
            'message' => 'Ticket updated successfully',
            'data' => new TicketResource($this->loadDetail($ticket->fresh())),
        ]);
    }

    public function destroy(Event $event, Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }

    public function reorder(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'distinct'],
            'orders.*.order' => ['required', 'integer', 'min:1'],
        ]);

        $ids = collect($validated['orders'])->pluck('id')->all();

        $belongCount = $event->tickets()->whereIn('id', $ids)->count();
        if ($belongCount !== count($ids)) {
            return response()->json(['message' => 'One or more tickets do not belong to this event.'], 422);
        }

        DB::transaction(function () use ($validated, $event) {
            foreach ($validated['orders'] as $order) {
                $event->tickets()->where('id', $order['id'])->update(['order_column' => $order['order']]);
            }
        });

        // Bulk query-builder updates skip model events, so bust the cache manually.
        ResponseCache::clear(['tickets']);

        return response()->json(['message' => 'Ticket order updated successfully']);
    }

    /**
     * Issue a batch of complimentary tickets. The order + item are created now;
     * attendees stream in via a queued job (poll batchStatus for progress).
     */
    public function bulkGenerate(BulkGenerateTicketRequest $request, Event $event, TicketPurchaseService $purchases): JsonResponse
    {
        $data = $request->validated();
        $data['event_id'] = $event->id;

        $order = $purchases->bulkGenerate($data);

        activity()
            ->performedOn($order)
            ->event('bulk_generated')
            ->withProperties([
                'batch_label' => $order->batch_label,
                'count' => (int) ($order->items()->first()?->quantity ?? 0),
            ])
            ->log('Bulk-generated complimentary tickets');

        return response()->json([
            'message' => 'Generating tickets.',
            'data' => [
                'order_ulid' => $order->ulid,
                'batch_label' => $order->batch_label,
                'batch_status' => $order->batch_status,
                'target' => (int) ($order->items()->first()?->quantity ?? 0),
            ],
        ], 202);
    }

    /**
     * Progress + result for a bulk batch (polled by the dialog).
     */
    public function batchStatus(Event $event, TicketOrder $ticketOrder): JsonResponse
    {
        abort_unless($ticketOrder->event_id === $event->id && $ticketOrder->source === 'admin', 404);

        return response()->json([
            'data' => [
                'batch_status' => $ticketOrder->batch_status,
                'batch_label' => $ticketOrder->batch_label,
                'target' => (int) ($ticketOrder->items()->first()?->quantity ?? 0),
                'generated' => $ticketOrder->attendees()->count(),
                'attendees' => $ticketOrder->batch_status === 'completed'
                    ? AttendeeResource::collection($ticketOrder->attendees()->with('ticket')->orderBy('id')->get())
                    : [],
            ],
        ]);
    }

    /**
     * CSV of the batch's attendees + their e-ticket links, for distribution.
     */
    public function exportBatch(Event $event, TicketOrder $ticketOrder): BinaryFileResponse
    {
        abort_unless($ticketOrder->event_id === $event->id && $ticketOrder->source === 'admin', 404);

        return Excel::download(
            new BulkTicketBatchExport($ticketOrder),
            "tickets-batch-{$ticketOrder->order_number}.csv",
            ExcelFormat::CSV,
        );
    }

    private function loadDetail(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'media',
            'validDays',
            'pricePhases',
            'sessions',
        ]);
    }

    private function handlePosterUpload(?string $tmpValue, bool $shouldDelete, Ticket $ticket): void
    {
        if ($shouldDelete) {
            $ticket->clearMediaCollection('poster');

            return;
        }

        if (! $tmpValue || ! Str::startsWith($tmpValue, 'tmp-')) {
            return;
        }

        $metadataPath = "tmp/uploads/{$tmpValue}/metadata.json";

        if (! Storage::disk('local')->exists($metadataPath)) {
            return;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filePath = "tmp/uploads/{$tmpValue}/{$metadata['original_name']}";

        if (! Storage::disk('local')->exists($filePath)) {
            return;
        }

        $ticket->clearMediaCollection('poster');
        $ticket->addMedia(Storage::disk('local')->path($filePath))->toMediaCollection('poster');

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$tmpValue}");
    }
}
